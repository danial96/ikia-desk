<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportChats extends BitrixCommand
{
    protected $signature = 'bitrix:import-chats
                            {--fresh : Delete existing Bitrix-imported chats before re-importing}
                            {--webhook= : Use a custom webhook URL instead of BITRIX_WEBHOOK env}';

    protected $description = 'Import Bitrix24 direct and group chats into the local messenger';

    private array $userMap = [];  // bitrix_id => local user id
    private int   $adminId;
    private int   $webhookUserId; // Bitrix user ID extracted from webhook URL

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Override webhook if --webhook option provided
        if ($customWebhook = $this->option('webhook')) {
            $this->webhook = rtrim($customWebhook, '/') . '/';
        }

        // Extract webhook user ID from URL: rest/USER_ID/TOKEN/
        preg_match('#/rest/(\d+)/#', $this->webhook, $m);
        $this->webhookUserId = (int)($m[1] ?? 155);

        $this->buildUserMap();

        if ($this->option('fresh')) {
            $this->info('Deleting existing Bitrix chats...');
            Conversation::whereNotNull('bitrix_chat_id')->each(function ($c) {
                $c->delete();
            });
        }

        $this->info("Webhook user: bitrix_id={$this->webhookUserId}");
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
        $all    = [];
        $limit  = 50;
        $offset = 0;

        do {
            $response = $this->bx('im.recent.list', ['LIMIT' => $limit, 'OFFSET' => $offset]);
            if (!$response) break;

            $items = $response['result']['items'] ?? [];
            if (empty($items)) break;

            foreach ($items as $item) {
                $t     = $item['type'] ?? '';
                $title = $item['title'] ?? '';
                if ($t === 'chat' && str_starts_with($title, 'Task:')) continue;
                if (in_array($t, ['user', 'chat'])) {
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
        $type  = $chat['type'];
        $rawId = (string)($chat['id'] ?? '');

        $otherId = $type === 'user'
            ? (int)$rawId
            : (int)preg_replace('/[^0-9]/', '', $rawId);

        $webhookLocalId = $this->userMap[$this->webhookUserId] ?? $this->adminId;

        if ($type === 'user') {
            // For direct chats: find existing conversation between the two local users
            // (avoids duplicates when same chat is imported from different webhook perspectives)
            $otherLocalId = $this->userMap[$otherId] ?? null;
            $conversation = $this->findOrCreateDirectConv($otherId, $webhookLocalId, $otherLocalId);
        } else {
            // Group chat: deduplicate by bitrix_chat_id
            $bitrixChatId = "chat:{$otherId}";
            $conversation = Conversation::where('bitrix_chat_id', $bitrixChatId)->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'bitrix_chat_id' => $bitrixChatId,
                    'type'           => 'group',
                    'name'           => $chat['title'] ?? 'Bitrix Group',
                    'created_by'     => $this->adminId,
                ]);
                // Fetch and add members
                $membersResp = $this->bx('im.chat.get', ['CHAT_ID' => $otherId]);
                foreach ($membersResp['result']['users'] ?? [] as $bxId) {
                    $lid = $this->userMap[(int)$bxId] ?? null;
                    if ($lid) $this->addMember($conversation->id, $lid);
                }
            } else {
                // Ensure webhook user is a member
                $this->addMember($conversation->id, $webhookLocalId);
            }
        }

        $dialogId = $type === 'user' ? $otherId : 'chat' . $otherId;
        return $this->importMessages($conversation, (string)$dialogId);
    }

    private function findOrCreateDirectConv(int $otherBxId, int $webhookLocalId, ?int $otherLocalId): Conversation
    {
        // 1. Members-based check is the most reliable cross-perspective dedup.
        //    "direct:{X}" is not globally unique — two different users can each have
        //    a DM with user X, resulting in two different conversations both keyed
        //    "direct:{X}". Members-based check is collision-free.
        if ($otherLocalId) {
            $conv = Conversation::where('type', 'direct')
                ->whereNotNull('bitrix_chat_id')
                ->whereHas('members', fn($q) => $q->where('user_id', $webhookLocalId))
                ->whereHas('members', fn($q) => $q->where('user_id', $otherLocalId))
                ->first();
            if ($conv) {
                $this->addMember($conv->id, $webhookLocalId);
                return $conv;
            }
        }

        // 2. Check by bitrix_chat_id from our perspective, but ONLY if the webhook
        //    user is already a member (avoids hijacking another user's DM with same person).
        $fromMyPov = "direct:{$otherBxId}";
        $conv = Conversation::where('bitrix_chat_id', $fromMyPov)
            ->whereHas('members', fn($q) => $q->where('user_id', $webhookLocalId))
            ->first();
        if ($conv) {
            return $conv;
        }

        // 3. Create a new conversation. Use a namespaced key if fromMyPov is already
        //    taken by another user's conversation to avoid a unique-constraint collision.
        $key = Conversation::where('bitrix_chat_id', $fromMyPov)->exists()
            ? "direct:{$this->webhookUserId}:{$otherBxId}"
            : $fromMyPov;

        $conv = Conversation::create([
            'bitrix_chat_id' => $key,
            'type'           => 'direct',
            'name'           => null,
            'created_by'     => $this->adminId,
        ]);
        $this->addMember($conv->id, $webhookLocalId);
        if ($otherLocalId) $this->addMember($conv->id, $otherLocalId);

        return $conv;
    }

    private function importMessages(Conversation $conversation, string $dialogId): int
    {
        $count       = 0;
        $lastId      = null;
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
            if (count($messages) < 50) break;
        } while (true);

        $allMessages = array_reverse($allMessages);

        foreach ($allMessages as $m) {
            $bitrixMsgId = (int)$m['id'];
            $authorBxId  = (int)($m['author_id'] ?? 0);
            $text        = trim($m['text'] ?? '');
            $date        = $this->parseDateTime($m['date'] ?? null);

            if ($authorBxId === 0 || empty($text)) continue;

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
