<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

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
            $parts    = explode(':', $conv->bitrix_chat_id);
            $type     = $parts[0];
            // For direct chats the key can be "direct:{otherId}" or "direct:{webhookUserId}:{otherId}"
            // — always take the last segment as the actual Bitrix user ID
            $rawId    = $type === 'direct' ? end($parts) : ($parts[1] ?? '');
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

            // Collect all file IDs on this page that need downloading
            $pageFileIds = [];
            foreach ($messages as $m) {
                foreach ($m['params']['FILE_ID'] ?? [] as $fid) {
                    if (isset($filesMap[(int)$fid])) $pageFileIds[] = (int)$fid;
                }
            }

            // Batch disk.file.get to get authenticated download URLs (50 per batch call)
            $downloadUrls = $this->batchGetDownloadUrls($pageFileIds);

            foreach ($messages as $m) {
                $fileIds = $m['params']['FILE_ID'] ?? [];
                if (empty($fileIds)) continue;

                $bitrixMsgId = (int)$m['id'];
                $authorBxId  = (int)($m['author_id'] ?? 0);
                $date        = $this->parseDateTime($m['date'] ?? null);
                $localUserId = $this->userMap[$authorBxId] ?? $this->adminId;

                $parts = [];
                foreach ($fileIds as $fileId) {
                    $fileId = (int)$fileId;
                    $info   = $filesMap[$fileId] ?? null;
                    if (!$info) continue;

                    $dlUrl    = $downloadUrls[$fileId] ?? null;
                    $localUrl = $this->downloadFile($info, $dlUrl);
                    if (!$localUrl) { $failed++; continue; }

                    $name    = $info['name'] ?? 'file';
                    $isImage = ($info['type'] ?? '') === 'image';
                    $isVoice = !empty($info['isVoiceNote']);

                    if ($isVoice) {
                        $durSecs = isset($info['duration']) ? (int)$info['duration'] : 0;
                        $durStr  = floor($durSecs / 60) . ':' . str_pad($durSecs % 60, 2, '0', STR_PAD_LEFT);
                        $parts[] = "[voice dur=\"{$durStr}\"]{$localUrl}[/voice]";
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

    // Batch-fetch authenticated download URLs for up to 50 file IDs per API call
    private function batchGetDownloadUrls(array $fileIds): array
    {
        if (empty($fileIds)) return [];

        $result = [];
        foreach (array_chunk(array_unique($fileIds), 50) as $chunk) {
            $cmd = [];
            foreach ($chunk as $id) {
                $cmd["f{$id}"] = "disk.file.get?id={$id}";
            }
            $resp = $this->bx('batch', ['halt' => 0, 'cmd' => $cmd]);
            foreach ($resp['result']['result'] ?? [] as $key => $res) {
                $id = (int)substr($key, 1); // strip 'f' prefix
                if (!empty($res['DOWNLOAD_URL'])) {
                    $result[$id] = $res['DOWNLOAD_URL'];
                }
            }
        }
        return $result;
    }

    private function downloadFile(array $info, ?string $url): ?string
    {
        $name = $info['name'] ?? 'file';
        $id   = (int)$info['id'];

        // Fall back to urlDownload if batch didn't return a URL
        if (!$url) $url = $info['urlDownload'] ?? null;
        if (!$url) return null;

        $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base     = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($name, PATHINFO_FILENAME));
        $filename = 'chat_' . $id . '_' . substr($base, 0, 60) . ($ext ? '.' . $ext : '');
        $savePath = $this->dir . '/' . $filename;

        // Skip if already downloaded and not corrupt (check for HTML content)
        if (!$this->option('fresh') && file_exists($savePath) && filesize($savePath) > 0) {
            $header = file_get_contents($savePath, false, null, 0, 5);
            if (strpos($header, '<') === false) {
                return $this->urlBase . '/' . $filename;
            }
            // File is corrupt HTML — re-download
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

        // Verify downloaded file is not an HTML error page
        if (!$ok || $curlErr || $code < 200 || $code >= 300 || filesize($savePath) === 0) {
            @unlink($savePath);
            $this->newLine();
            $this->warn("  FAIL [{$code}] file_id={$id} {$name}: $curlErr");
            return null;
        }

        $header = file_get_contents($savePath, false, null, 0, 5);
        if (strpos($header, '<') !== false) {
            @unlink($savePath);
            $this->newLine();
            $this->warn("  HTML response for file_id={$id} {$name} (auth failed)");
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
