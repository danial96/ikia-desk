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
        $response = $this->bx('im.dialog.messages.get', [
            'DIALOG_ID' => 'chat' . $task->chat_id,
            'LIMIT'     => 50,
        ]);

        if (!$response) return 0;

        $messages = $response['result']['messages'] ?? [];
        if (empty($messages)) return 0;

        // API returns newest-first; store oldest-first
        $messages = array_reverse($messages);

        $count = 0;
        foreach ($messages as $m) {
            $bitrixMsgId = (int)$m['id'];
            $authorBxId  = (int)($m['author_id'] ?? 0);
            $isSystem    = $authorBxId === 0;
            $userId      = $isSystem ? null : ($this->userMap[$authorBxId] ?? null);
            $text        = $m['text'] ?? '';
            $date        = $this->parseDateTime($m['date'] ?? null);
            $files       = $m['files'] ?? [];

            // Skip empty system messages and migration system notices
            if ($isSystem && (empty(trim($text)) ||
                str_contains($text, 'Task chat has been created') ||
                str_contains($text, 'read the previous comments'))) continue;

            $fileIds = $this->importChatFiles($task, $files, $userId);

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

    /** Handle files array from IM chat messages */
    private function importChatFiles(Task $task, array $files, ?int $userId): array
    {
        $fileIds = [];

        foreach ($files as $f) {
            $bitrixFileId = (int)($f['id'] ?? 0);
            if (!$bitrixFileId) continue;

            $existing = TaskFile::where('bitrix_file_id', $bitrixFileId)->first();
            if ($existing) {
                $fileIds[] = $existing->id;
                continue;
            }

            $downloadUrl = $f['urlDownload'] ?? $f['url'] ?? '';
            if ($downloadUrl && !str_starts_with($downloadUrl, 'http')) {
                $downloadUrl = $this->bitrixDomain . $downloadUrl;
            }

            $file = TaskFile::create([
                'task_id'             => $task->id,
                'uploaded_by'         => $userId ?? $this->adminId,
                'bitrix_file_id'      => $bitrixFileId,
                'name'                => $f['name'] ?? $f['originalName'] ?? 'attachment',
                'size'                => (int)($f['size'] ?? 0),
                'mime_type'           => $f['type'] ?? null,
                'bitrix_download_url' => $downloadUrl ?: null,
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
