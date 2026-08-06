<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskFile;
use App\Models\User;

class ImportComments extends BitrixCommand
{
    protected $signature = 'bitrix:import-comments
                            {--limit=0  : Max tasks to process (0 = all)}
                            {--task=0   : Import comments for a single task by its LOCAL id}
                            {--fresh    : Delete existing imported comments before re-importing}';

    protected $description = 'Import Bitrix task comments (forum + IM chat) and file attachments';

    private array $userMap = [];
    private int   $adminId;
    private string $bitrixDomain;

    public function handle(): int
    {
        $this->buildUserMap();

        // Derive the Bitrix domain from the webhook URL for building file download URLs
        $parsed = parse_url($this->webhook);
        $this->bitrixDomain = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        if ($singleId = (int)$this->option('task')) {
            $task = Task::findOrFail($singleId);
            $this->importTaskComments($task);
            return 0;
        }

        $limit = (int)$this->option('limit');
        $fresh = $this->option('fresh');

        // Process ALL tasks that came from Bitrix (both chat-based and forum-based)
        $query = Task::whereNotNull('bitrix_id');
        if ($limit > 0) $query->limit($limit);

        $tasks = $query->get();
        $this->info("Processing " . $tasks->count() . " Bitrix tasks...");

        $bar = $this->output->createProgressBar($tasks->count());
        $bar->start();

        $totalComments = 0;

        foreach ($tasks as $task) {
            try {
                if ($fresh) {
                    TaskComment::where('task_id', $task->id)
                               ->whereNotNull('bitrix_id')
                               ->forceDelete();
                }
                $totalComments += $this->importTaskComments($task);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("  Task {$task->id} (bitrix #{$task->bitrix_id}): " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Total comments imported: $totalComments");
        return 0;
    }

    private function importTaskComments(Task $task): int
    {
        // Always fetch forum comments (old system); additionally fetch IM messages if chat exists.
        // Both may have real content — tasks migrated to IM still retain forum history.
        $count = $this->importForumComments($task);
        if ($task->chat_id) {
            $count += $this->importChatComments($task);
        }
        return $count;
    }

    /** New-style tasks: IM chat messages via im.dialog.messages.get */
    private function importChatComments(Task $task): int
    {
        $allMessages = [];
        $lastId = null;

        // Paginate: API returns newest-first, 50 per page. Use LAST_ID to walk backwards.
        do {
            $params = ['DIALOG_ID' => 'chat' . $task->chat_id, 'LIMIT' => 50];
            if ($lastId !== null) {
                $params['LAST_ID'] = $lastId;
            }

            $response = $this->bx('im.dialog.messages.get', $params);
            if (!$response) break;

            $messages = $response['result']['messages'] ?? [];
            if (empty($messages)) break;

            $allMessages = array_merge($allMessages, $messages);

            if (count($messages) < 50) break; // last page

            // Walk further back: use the smallest (oldest) message ID in this batch
            $lastId = (int)min(array_column($messages, 'id'));
        } while (true);

        if (empty($allMessages)) return 0;

        // Deduplicate (pagination overlap possible) and sort oldest-first
        $seen = [];
        $unique = [];
        foreach ($allMessages as $m) {
            if (!isset($seen[$m['id']])) {
                $seen[$m['id']] = true;
                $unique[] = $m;
            }
        }
        usort($unique, fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);

        $count = 0;
        foreach ($unique as $m) {
            $bitrixMsgId = (int)$m['id'];
            $authorBxId  = (int)($m['author_id'] ?? 0);
            $isSystem    = $authorBxId === 0;
            $userId      = $isSystem ? null : ($this->userMap[$authorBxId] ?? null);
            $text        = $m['text'] ?? '';
            $date        = $this->parseDateTime($m['date'] ?? null);

            // IM file attachments are Disk file IDs stored in params.FILE_ID (not the files array)
            $diskFileIds = $m['params']['FILE_ID'] ?? [];
            if (!is_array($diskFileIds)) {
                $diskFileIds = $diskFileIds ? [(int)$diskFileIds] : [];
            }

            // Skip empty system messages and migration system notices
            if ($isSystem && (empty(trim($text)) ||
                str_contains($text, 'Task chat has been created') ||
                str_contains($text, 'read the previous comments'))) continue;

            $fileIds = $diskFileIds ? $this->importDiskFileIds($task, $diskFileIds, $userId) : [];

            TaskComment::updateOrCreate(
                ['bitrix_id' => $bitrixMsgId],
                [
                    'task_id'    => $task->id,
                    'user_id'    => $userId ?? $this->adminId,
                    'content'    => $text,
                    'is_system'  => $isSystem,
                    'files'      => $fileIds ?: null,
                    'created_at' => $date ?? now(),
                    'updated_at' => $date ?? now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /** Old-style tasks: forum comments via task.commentitem.getlist */
    private function importForumComments(Task $task): int
    {
        // Note: ORDER parameter is not supported by this API and causes error #8
        $response = $this->bx('task.commentitem.getlist', [
            'TASK_ID' => $task->bitrix_id,
        ]);

        if (!$response) return 0;

        $comments = $response['result'] ?? [];
        if (empty($comments)) return 0;

        $count = 0;
        foreach ($comments as $c) {
            $bitrixCommentId = (int)$c['ID'];
            $authorBxId      = (int)($c['AUTHOR_ID'] ?? 0);
            $userId          = $authorBxId ? ($this->userMap[$authorBxId] ?? null) : null;
            $text            = $c['POST_MESSAGE'] ?? '';
            $date            = $this->parseDateTime($c['POST_DATE'] ?? null);
            $attachedObjects = $c['ATTACHED_OBJECTS'] ?? [];

            if (empty(trim($text)) && empty($attachedObjects)) continue;

            $fileIds = $this->importAttachedObjects($task, $attachedObjects, $userId);

            TaskComment::updateOrCreate(
                ['bitrix_id' => $bitrixCommentId],
                [
                    'task_id'    => $task->id,
                    'user_id'    => $userId ?? $this->adminId,
                    'content'    => $text,
                    'is_system'  => false,
                    'files'      => $fileIds ?: null,
                    'created_at' => $date ?? now(),
                    'updated_at' => $date ?? now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /** Handle ATTACHED_OBJECTS from old-style forum comments */
    private function importAttachedObjects(Task $task, array $attachedObjects, ?int $userId): array
    {
        $fileIds = [];

        foreach ($attachedObjects as $attachObj) {
            $bitrixAttachId = (int)($attachObj['ATTACHMENT_ID'] ?? 0);
            if (!$bitrixAttachId) continue;

            $existing = TaskFile::where('bitrix_file_id', $bitrixAttachId)->first();
            if ($existing) {
                $fileIds[] = $existing->id;
                continue;
            }

            $downloadUrl = $attachObj['DOWNLOAD_URL'] ?? '';
            if ($downloadUrl && !str_starts_with($downloadUrl, 'http')) {
                $downloadUrl = $this->bitrixDomain . $downloadUrl;
            }

            $file = TaskFile::create([
                'task_id'             => $task->id,
                'uploaded_by'         => $userId ?? $this->adminId,
                'bitrix_file_id'      => $bitrixAttachId,
                'name'                => $attachObj['NAME'] ?? 'attachment',
                'size'                => (int)($attachObj['SIZE'] ?? 0),
                'bitrix_download_url' => $downloadUrl ?: null,
            ]);

            $fileIds[] = $file->id;
        }

        return $fileIds;
    }

    /** Resolve Bitrix Disk file IDs via disk.file.get and create/reuse task_files records */
    private function importDiskFileIds(Task $task, array $diskFileIds, ?int $userId): array
    {
        $fileIds = [];

        foreach ($diskFileIds as $rawId) {
            $diskId = (int)$rawId;
            if (!$diskId) continue;

            $existing = TaskFile::where('bitrix_file_id', $diskId)->first();
            if ($existing) {
                $fileIds[] = $existing->id;
                continue;
            }

            $res = $this->bx('disk.file.get', ['id' => $diskId]);
            if (!$res || empty($res['result'])) continue;

            $info = $res['result'];

            $file = TaskFile::create([
                'task_id'             => $task->id,
                'uploaded_by'         => $userId ?? $this->adminId,
                'bitrix_file_id'      => $diskId,
                'name'                => $info['NAME'] ?? 'attachment',
                'size'                => (int)($info['SIZE'] ?? 0),
                'bitrix_download_url' => $info['DOWNLOAD_URL'] ?? null,
            ]);

            $fileIds[] = $file->id;
        }

        return $fileIds;
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
