<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class ImportTasks extends BitrixCommand
{
    protected $signature = 'bitrix:import-tasks
                            {--start=0 : Start offset}
                            {--limit=0 : Total tasks to import (0 = all)}
                            {--batch=50 : Batch size per API call}';

    protected $description = 'Import all tasks from Bitrix24 into the local database';

    private array $userMap    = [];   // bitrix_id => local user id
    private array $projectMap = [];   // bitrix_group_id => local project id
    private int   $adminId;

    public function handle(): int
    {
        $this->info('Preparing user & project maps...');
        $this->buildMaps();

        $batchSize  = max(1, min(50, (int)$this->option('batch')));
        $startAt    = (int)$this->option('start');
        $maxImport  = (int)$this->option('limit');

        $select = [
            'ID','TITLE','DESCRIPTION','STATUS','PRIORITY',
            'DEADLINE','START_DATE_PLAN','DATE_START','CLOSED_DATE',
            'CREATED_DATE','CREATED_BY','RESPONSIBLE_ID',
            'GROUP_ID','ACCOMPLICES','AUDITORS',
            'UF_TASK_WEBDAV_FILES','CHAT_ID',
            'CHECKLIST','TAGS',
            'TIME_ESTIMATE','TIME_SPENT_IN_CHANGING',
            'ALLOW_CHANGE_DEADLINE','TASK_CONTROL',
            'STAGE_ID','SORTING',
        ];

        $this->info("Fetching tasks from Bitrix24 (batch=$batchSize, start=$startAt)...");

        $imported = 0;
        $skipped  = 0;
        $offset   = $startAt;

        do {
            $response = $this->bx('tasks.task.list', [
                'order'  => ['ID' => 'ASC'],
                'filter' => [],
                'select' => $select,
                'start'  => $offset,
            ]);

            if (!$response) break;

            $tasks = $response['result']['tasks'] ?? [];
            if (empty($tasks)) break;

            foreach ($tasks as $t) {
                try {
                    $this->importTask($t);
                    $imported++;
                } catch (\Throwable $e) {
                    $this->warn("  Task {$t['id']} failed: " . $e->getMessage());
                    $skipped++;
                }

                if ($maxImport > 0 && ($imported + $skipped) >= $maxImport) break 2;
            }

            $this->line("  Offset $offset — imported $imported so far...");

            $next   = $response['next']  ?? null;
            $total  = $response['total'] ?? '?';
            $offset = $next;

            if ($imported === 1 || $imported % 200 === 0) {
                $this->info("  Progress: $imported / $total");
            }

        } while ($offset !== null && $offset !== false);

        $this->info("Import complete. Imported: $imported  |  Skipped: $skipped");
        return 0;
    }

    private function importTask(array $t): void
    {
        $bitrixId      = (int)$t['id'];
        $responsibleBx = (int)($t['responsibleId'] ?? 0);
        $creatorBx     = (int)($t['creatorId']     ?? 0);
        $groupBx       = (int)($t['groupId']       ?? 0);

        $assignedTo = $this->userMap[$responsibleBx] ?? null;
        $createdBy  = $this->userMap[$creatorBx]     ?? $this->adminId;
        $projectId  = $groupBx ? ($this->projectMap[$groupBx] ?? null) : null;

        // Map Bitrix status (1-6) → our enum
        $status = $this->mapStatus((string)($t['status'] ?? '1'));

        // Map Bitrix priority (0-2) → our enum
        $priority = $this->mapPriority((string)($t['priority'] ?? '1'));

        $task = Task::updateOrCreate(
            ['bitrix_id' => $bitrixId],
            [
                'title'                 => $t['title'] ?? "Task #$bitrixId",
                'description'           => $t['description'] ?? null,
                'status'                => $status,
                'priority'              => $priority,
                'project_id'            => $projectId,
                'created_by'            => $createdBy,
                'assigned_to'           => $assignedTo,
                'deadline'              => $this->parseDateTime($t['deadline'] ?? null),
                'start_date'            => $this->parseDateTime($t['startDatePlan'] ?? $t['dateStart'] ?? null),
                'closed_date'           => $this->parseDateTime($t['closedDate'] ?? null),
                'chat_id'               => (int)($t['chatId'] ?? 0) ?: null,
                'bitrix_group_id'       => $groupBx ?: null,
                'time_estimate'         => (int)($t['timeEstimate'] ?? 0),
                'time_spent'            => (int)($t['timeSpentInChanging'] ?? 0),
                'allow_change_deadline' => ($t['allowChangeDeadline'] ?? 'N') === 'Y',
                'task_control'          => ($t['taskControl']          ?? 'N') === 'Y',
                'stage_id'              => $t['stageId'] ?? null,
                'sort_index'            => (int)($t['sorting'] ?? 0),
                'created_at'            => $this->parseDateTime($t['createdDate'] ?? null) ?? now(),
            ]
        );

        // Sync participants (accomplices)
        $participantIds = $this->mapUserIds((array)($t['accomplices'] ?? []));
        if ($participantIds) {
            $task->members()->syncWithoutDetaching($participantIds);
        }

        // Sync observers (auditors)
        $observerIds = $this->mapUserIds((array)($t['auditors'] ?? []));
        if ($observerIds) {
            $task->observers()->syncWithoutDetaching($observerIds);
        }
    }

    private function mapStatus(string $s): string
    {
        // Bitrix: 1=New, 2=In Progress, 3=In Progress, 4=Review/Awaiting, 5=Completed, 6=Deferred
        return match($s) {
            '1'     => 'new',
            '2','3' => 'in_progress',
            '4'     => 'review',
            '5'     => 'completed',
            '6'     => 'paused',
            default => 'new',
        };
    }

    private function mapPriority(string $p): string
    {
        return match($p) {
            '0'     => 'low',
            '1'     => 'medium',
            '2'     => 'urgent',
            default => 'medium',
        };
    }

    private function mapUserIds(array $bxIds): array
    {
        return array_values(array_filter(
            array_map(fn($bid) => $this->userMap[(int)$bid] ?? null, $bxIds)
        ));
    }

    private function buildMaps(): void
    {
        User::whereNotNull('bitrix_id')->select('id','bitrix_id')->each(
            fn($u) => $this->userMap[$u->bitrix_id] = $u->id
        );

        Project::whereNotNull('bitrix_id')->select('id','bitrix_id')->each(
            fn($p) => $this->projectMap[$p->bitrix_id] = $p->id
        );

        $this->adminId = User::where('role','super_admin')->value('id')
                      ?? User::first()?->id
                      ?? 1;

        $this->info("  Users mapped: " . count($this->userMap));
        $this->info("  Projects mapped: " . count($this->projectMap));
    }

    private function parseDateTime(?string $v): ?string
    {
        if (!$v || $v === '0000-00-00T00:00:00+00:00') return null;
        try { return (new \DateTime($v))->format('Y-m-d H:i:s'); }
        catch (\Throwable) { return null; }
    }
}
