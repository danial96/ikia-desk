<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

class ImportComments extends BitrixCommand
{
    protected $signature = 'bitrix:import-comments
                            {--limit=0  : Max tasks to process (0 = all)}
                            {--task=0   : Import comments for a single task by its LOCAL id}
                            {--fresh    : Delete existing imported comments before re-importing}';

    protected $description = 'Import Bitrix IM chat messages for all tasks as local comments';

    private array $userMap = [];
    private int   $adminId;

    public function handle(): int
    {
        $this->buildUserMap();

        if ($singleId = (int)$this->option('task')) {
            $task = Task::findOrFail($singleId);
            $this->importTaskComments($task);
            return 0;
        }

        $limit = (int)$this->option('limit');
        $fresh = $this->option('fresh');

        $query = Task::whereNotNull('chat_id')->whereNotNull('bitrix_id');
        if ($limit > 0) $query->limit($limit);

        $tasks = $query->get();
        $this->info("Processing " . $tasks->count() . " tasks with a chat_id...");

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
        $chatId = $task->chat_id;
        if (!$chatId) return 0;

        $response = $this->bx('im.dialog.messages.get', [
            'DIALOG_ID' => 'chat' . $chatId,
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

            // Skip empty system messages that carry no useful text
            if ($isSystem && empty(trim($text))) continue;

            TaskComment::updateOrCreate(
                ['bitrix_id' => $bitrixMsgId],
                [
                    'task_id'    => $task->id,
                    'user_id'    => $userId ?? $this->adminId,
                    'content'    => $text,
                    'is_system'  => $isSystem,
                    'created_at' => $date ?? now(),
                    'updated_at' => $date ?? now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function buildUserMap(): void
    {
        User::whereNotNull('bitrix_id')->select('id','bitrix_id')->each(
            fn($u) => $this->userMap[$u->bitrix_id] = $u->id
        );

        $this->adminId = User::where('role','super_admin')->value('id')
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
