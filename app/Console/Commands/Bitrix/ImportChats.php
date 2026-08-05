<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportChats extends BitrixCommand
{
    protected $signature = 'bitrix:import-chats
                            {--fresh : Delete existing Bitrix-imported chats before re-importing}';

    protected $description = 'Import Bitrix24 direct and group chats into the local messenger';

    private array $userMap = [];  // bitrix_id => local user id
    private int   $adminId;

    public function handle(): int
    {
        $this->buildUserMap();

        if ($this->option('fresh')) {
            $this->info('Deleting existing Bitrix chats...');
            Conversation::whereNotNull('bitrix_chat_id')->each(function ($c) {
                $c->delete();
            });
        }

        $this->info('Fetching Bitrix24 IM chats...');
        $chats = $this->fetchAllChats();

        $this->info("Found " . count($chats) . " chats. Importing...");
        $bar = $this->output->createProgressBar(count($chats));
        $bar->start();

        $totalMessages = 0;

        foreach ($chats as $chat) {
            try {
                $totalMessages += $this->importChat($chat);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("  Chat {$chat['id']}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Total messages imported: $totalMessages");
        return 0;
    }

    private function fetchAllChats(): array
    {
        $all   = [];
        $limit = 50;
        $offset = 0;

        do {
            $response = $this->bx('im.recent.list', ['LIMIT' => $limit, 'OFFSET' => $offset]);
            if (!$response) break;

            $items = $response['result']['items'] ?? [];
            if (empty($items)) break;

            // Only direct and group chats (skip task chats — imported as task comments)
            foreach ($items as $item) {
                if (in_array($item['type'] ?? '', ['user', 'chat'])) {
                    $all[] = $item;
                }
            }

            $offset += $limit;
            $total = $response['result']['totalCount'] ?? 0;
        } while (count($all) < $total && count($items) === $limit);

        return $all;
    }

    private function importChat(array $chat): int
    {
        $type    = $chat['type'];           // 'user' or 'chat'
        $otherId = (int)($chat['id'] ?? 0); // for 'user': the other user's bitrix_id; for 'chat': chatId

        // Derive a stable identifier for deduplication
        $bitrixChatId = $type === 'user'
            ? "direct:{$otherId}"
            : "chat:{$otherId}";

        // Get or create the local conversation
        $conversation = Conversation::where('bitrix_chat_id', $bitrixChatId)->first();

        if (!$conversation) {
            $localUserId = $type === 'user'
                ? ($this->userMap[$otherId] ?? null)
                : null;

            $conversation = Conversation::create([
                'bitrix_chat_id' => $bitrixChatId,
                'type'           => $type === 'user' ? 'direct' : 'group',
                'name'           => $type === 'chat' ? ($chat['title'] ?? 'Bitrix Group') : null,
                'created_by'     => $this->adminId,
            ]);

            // Add members
            if ($type === 'user') {
                // Direct chat: webhook user + the other user
                $webhookLocalId = $this->userMap[155] ?? $this->adminId;
                $this->addMember($conversation->id, $webhookLocalId);
                if ($localUserId) {
                    $this->addMember($conversation->id, $localUserId);
                }
            } else {
                // Group chat: fetch member list
                $membersResp = $this->bx('im.chat.get', ['CHAT_ID' => $otherId]);
                $memberBxIds = $membersResp['result']['users'] ?? [];
                foreach ($memberBxIds as $bxId) {
                    $lid = $this->userMap[(int)$bxId] ?? null;
                    if ($lid) $this->addMember($conversation->id, $lid);
                }
            }
        }

        // Fetch messages for this chat
        $dialogId = $type === 'user' ? $otherId : 'chat' . $otherId;
        return $this->importMessages($conversation, (string)$dialogId);
    }

    private function importMessages(Conversation $conversation, string $dialogId): int
    {
        $count  = 0;
        $lastId = null;

        // Paginate through all messages oldest-first by starting from the end and reversing
        $allMessages = [];

        do {
            $params = ['DIALOG_ID' => $dialogId, 'LIMIT' => 50];
            if ($lastId) $params['LAST_ID'] = $lastId;

            $response = $this->bx('im.dialog.messages.get', $params);
            if (!$response) break;

            $messages = $response['result']['messages'] ?? [];
            if (empty($messages)) break;

            $allMessages = array_merge($allMessages, $messages);
            $lastId = end($messages)['id'] ?? null;

            // Stop if fewer messages than limit (last page)
            if (count($messages) < 50) break;
        } while (true);

        // Reverse to get oldest-first order
        $allMessages = array_reverse($allMessages);

        foreach ($allMessages as $m) {
            $bitrixMsgId = (int)$m['id'];
            $authorBxId  = (int)($m['author_id'] ?? 0);
            $text        = trim($m['text'] ?? '');
            $date        = $this->parseDateTime($m['date'] ?? null);

            // Skip system messages
            if ($authorBxId === 0) continue;
            if (empty($text)) continue;

            $localUserId = $this->userMap[$authorBxId] ?? $this->adminId;

            Message::updateOrCreate(
                ['bitrix_id' => $bitrixMsgId],
                [
                    'conversation_id' => $conversation->id,
                    'user_id'         => $localUserId,
                    'content'         => $text,
                    'created_at'      => $date ?? now(),
                    'updated_at'      => $date ?? now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function addMember(int $conversationId, int $userId): void
    {
        DB::table('conversation_members')->insertOrIgnore([
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    private function buildUserMap(): void
    {
        User::whereNotNull('bitrix_id')->select('id', 'bitrix_id')->each(
            fn($u) => $this->userMap[$u->bitrix_id] = $u->id
        );

        $this->adminId = User::where('role', 'super_admin')->value('id')
                      ?? User::first()?->id
                      ?? 1;

        $this->info("User map built: " . count($this->userMap) . " users");
    }

    private function parseDateTime(?string $v): ?string
    {
        if (!$v) return null;
        try { return (new \DateTime($v))->format('Y-m-d H:i:s'); }
        catch (\Throwable) { return null; }
    }
}
