<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DownloadChatFiles extends BitrixCommand
{
    protected $signature = 'bitrix:download-chat-files
                            {--fresh : Re-download already downloaded files}';

    protected $description = 'Download Bitrix IM file/image attachments and update message content';

    private array $userMap = [];
    private int   $adminId;
    private string $dir;
    private string $urlBase = '/uploads/bitrix/chat';

    public function handle(): int
    {
        $this->buildUserMap();

        $this->dir = public_path('uploads/bitrix/chat');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }

        $conversations = Conversation::whereNotNull('bitrix_chat_id')->get();
        $this->info("Found {$conversations->count()} conversations to process...");

        $bar = $this->output->createProgressBar($conversations->count());
        $bar->start();

        $totalDone = $totalFailed = 0;

        foreach ($conversations as $conv) {
            [$type, $rawId] = explode(':', $conv->bitrix_chat_id, 2);
            $dialogId = $type === 'direct' ? $rawId : 'chat' . $rawId;

            [$done, $failed] = $this->processDialog($conv, $dialogId);
            $totalDone   += $done;
            $totalFailed += $failed;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Files downloaded: $totalDone | Failed: $totalFailed");
        return 0;
    }

    private function processDialog(Conversation $conv, string $dialogId): array
    {
        $downloaded = $failed = 0;
        $lastId = null;

        do {
            $params = ['DIALOG_ID' => $dialogId, 'LIMIT' => 50];
            if ($lastId) $params['LAST_ID'] = $lastId;

            $response = $this->bx('im.dialog.messages.get', $params);
            if (!$response) break;

            $messages = $response['result']['messages'] ?? [];
            if (empty($messages)) break;

            // Build file_id => file_info map from this page's files array
            $filesMap = [];
            foreach ($response['result']['files'] ?? [] as $f) {
                $filesMap[(int)$f['id']] = $f;
            }

            foreach ($messages as $m) {
                $fileIds = $m['params']['FILE_ID'] ?? [];
                if (empty($fileIds)) continue;

                $bitrixMsgId = (int)$m['id'];
                $authorBxId  = (int)($m['author_id'] ?? 0);
                $date        = $this->parseDateTime($m['date'] ?? null);
                $localUserId = $this->userMap[$authorBxId] ?? $this->adminId;

                $parts = [];
                foreach ($fileIds as $fileId) {
                    $info = $filesMap[(int)$fileId] ?? null;
                    if (!$info) {
                        $this->newLine();
                        $this->warn("  No file info for file_id=$fileId in msg=$bitrixMsgId");
                        continue;
                    }

                    $localUrl = $this->downloadFile($info);
                    if (!$localUrl) { $failed++; continue; }

                    $name        = $info['name'] ?? 'file';
                    $isImage     = ($info['type'] ?? '') === 'image';
                    $isVoice     = !empty($info['isVoiceNote']);
                    $dur         = '';

                    if ($isVoice) {
                        $parts[] = "[voice]{$localUrl}[/voice]";
                    } elseif ($isImage) {
                        $parts[] = "[img]{$localUrl}[/img]";
                    } else {
                        $safeName = addslashes($name);
                        $parts[] = "[file name=\"{$safeName}\"]{$localUrl}[/file]";
                    }
                    $downloaded++;
                }

                if (empty($parts)) continue;

                $content = implode("\n", $parts);
                $text    = trim($m['text'] ?? '');
                if ($text) $content .= "\n" . $text;

                Message::updateOrCreate(
                    ['bitrix_id' => $bitrixMsgId],
                    [
                        'conversation_id' => $conv->id,
                        'user_id'         => $localUserId,
                        'content'         => $content,
                        'created_at'      => $date ?? now(),
                        'updated_at'      => $date ?? now(),
                    ]
                );
            }

            $lastId = end($messages)['id'] ?? null;
            if (count($messages) < 50) break;

        } while (true);

        return [$downloaded, $failed];
    }

    private function downloadFile(array $info): ?string
    {
        $url  = $info['urlDownload'] ?? null;
        $name = $info['name'] ?? 'file';
        $id   = (int)$info['id'];

        if (!$url) return null;

        $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base     = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($name, PATHINFO_FILENAME));
        $filename = 'chat_' . $id . '_' . substr($base, 0, 60) . ($ext ? '.' . $ext : '');
        $savePath = $this->dir . '/' . $filename;

        // Skip if already downloaded
        if (!$this->option('fresh') && file_exists($savePath) && filesize($savePath) > 0) {
            return $this->urlBase . '/' . $filename;
        }

        $fp = fopen($savePath, 'wb');
        if (!$fp) return null;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        $ok      = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $curlErr || $code < 200 || $code >= 300 || filesize($savePath) === 0) {
            @unlink($savePath);
            $this->newLine();
            $this->warn("  FAIL [{$code}] file_id={$id} {$name}: $curlErr");
            return null;
        }

        return $this->urlBase . '/' . $filename;
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
