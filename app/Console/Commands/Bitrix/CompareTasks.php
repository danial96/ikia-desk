<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * READ-ONLY. Compares every Bitrix task with its desk copy, field by field, and reports where the two
 * differ — and, for each difference, whether the task was also changed inside the desk since the
 * import (task_activities has rows only for edits made in the desk; imports never log any).
 * Nothing is written.
 */
class CompareTasks extends BitrixCommand
{
    protected $signature = 'bitrix:compare-tasks {--webhook= : use this webhook instead of BITRIX_WEBHOOK}';
    protected $description = 'Report (read-only) how the desk tasks differ from Bitrix, field by field';

    public function handle(): int
    {
        if ($w = $this->option('webhook')) $this->webhook = rtrim($w, '/') . '/';

        $users    = User::whereNotNull('bitrix_id')->pluck('id', 'bitrix_id')->all();
        $projects = Project::whereNotNull('bitrix_id')->pluck('id', 'bitrix_id')->all();
        $statusMap   = ['1' => 'new', '2' => 'pending', '3' => 'in_progress', '4' => 'reviewing', '5' => 'completed', '6' => 'paused'];
        $priorityMap = ['0' => 'low', '1' => 'medium', '2' => 'urgent'];
        $tz = new \DateTimeZone(config('app.timezone'));

        $this->info('Reading every task from Bitrix…');
        $remote = [];
        foreach ($this->bxAll('tasks.task.list', [
            'order'  => ['ID' => 'ASC'],
            'select' => ['ID', 'TITLE', 'STATUS', 'PRIORITY', 'DEADLINE', 'CREATED_BY', 'RESPONSIBLE_ID', 'GROUP_ID', 'ACCOMPLICES', 'AUDITORS', 'CHANGED_DATE', 'CLOSED_DATE'],
        ]) as $t) {
            $remote[(int)($t['id'] ?? $t['ID'] ?? 0)] = $t;
        }
        $this->info('  ' . count($remote) . ' tasks in Bitrix');

        $edited = DB::table('task_activities')->distinct()->pluck('task_id')->flip()->all();
        $members   = DB::table('task_members')->get()->groupBy('task_id')->map(fn($g) => $g->pluck('user_id')->sort()->values()->all())->all();
        $observers = DB::table('task_observers')->get()->groupBy('task_id')->map(fn($g) => $g->pluck('user_id')->sort()->values()->all())->all();

        $fields = ['title', 'status', 'priority', 'deadline', 'creator', 'assignee', 'project', 'participants', 'observers'];
        $diff = array_fill_keys($fields, ['total' => 0, 'also_edited_in_desk' => 0, 'sample' => []]);
        $status4 = 0; $missing = 0; $checked = 0; $anyDiff = 0; $anyDiffEdited = 0; $statusPairs = [];

        Task::withTrashed()->whereNotNull('bitrix_id')->select('id', 'bitrix_id', 'title', 'status', 'priority', 'deadline', 'created_by', 'assigned_to', 'project_id', 'deleted_at')
            ->chunkById(500, function ($tasks) use (&$diff, &$status4, &$missing, &$checked, &$anyDiff, &$anyDiffEdited, &$statusPairs, $remote, $users, $projects, $statusMap, $priorityMap, $tz, $edited, $members, $observers) {
                foreach ($tasks as $task) {
                    $r = $remote[(int)$task->bitrix_id] ?? null;
                    if (!$r) { $missing++; continue; }
                    $checked++;
                    $bad = [];

                    if (trim((string)($r['title'] ?? '')) !== trim((string)$task->title)) $bad[] = 'title';

                    $bs = (string)($r['status'] ?? '');
                    if ($bs === '4') $status4++;
                    if (($statusMap[$bs] ?? 'new') !== $task->status) { $bad[] = 'status'; $k = $bs . ' → ' . $task->status; $statusPairs[$k] = ($statusPairs[$k] ?? 0) + 1; }

                    if (($priorityMap[(string)($r['priority'] ?? '1')] ?? 'medium') !== $task->priority) $bad[] = 'priority';

                    $rd = $r['deadline'] ?? null;
                    $rd = ($rd && $rd !== '0000-00-00T00:00:00+00:00') ? (new \DateTime($rd))->setTimezone($tz)->format('Y-m-d H:i') : null;
                    $ld = $task->deadline ? $task->deadline->copy()->setTimezone($tz)->format('Y-m-d H:i') : null;
                    if ($rd !== $ld) $bad[] = 'deadline';

                    if (($users[(int)($r['createdBy'] ?? 0)] ?? null) !== (int)$task->created_by && isset($users[(int)($r['createdBy'] ?? 0)])) $bad[] = 'creator';
                    if (($users[(int)($r['responsibleId'] ?? 0)] ?? null) !== ($task->assigned_to ? (int)$task->assigned_to : null)) $bad[] = 'assignee';

                    $g = (int)($r['groupId'] ?? 0);
                    if (($g ? ($projects[$g] ?? null) : null) !== ($task->project_id ? (int)$task->project_id : null)) $bad[] = 'project';

                    $rm = collect((array)($r['accomplices'] ?? []))->map(fn($b) => $users[(int)$b] ?? null)->filter()->sort()->values()->all();
                    if ($rm !== ($members[$task->id] ?? [])) $bad[] = 'participants';
                    $ro = collect((array)($r['auditors'] ?? []))->map(fn($b) => $users[(int)$b] ?? null)->filter()->sort()->values()->all();
                    if ($ro !== ($observers[$task->id] ?? [])) $bad[] = 'observers';

                    if ($bad) {
                        $anyDiff++;
                        $isEdited = isset($edited[$task->id]);
                        if ($isEdited) $anyDiffEdited++;
                        foreach ($bad as $f) {
                            $diff[$f]['total']++;
                            if ($isEdited) $diff[$f]['also_edited_in_desk']++;
                            if (count($diff[$f]['sample']) < 8) $diff[$f]['sample'][] = (int)$task->bitrix_id;
                        }
                    }
                }
            });

        $notInDesk = count(array_diff_key($remote, Task::withTrashed()->whereNotNull('bitrix_id')->pluck('id', 'bitrix_id')->all()));
        $this->newLine();
        $this->info(json_encode([
            'bitrix_tasks'                => count($remote),
            'compared'                    => $checked,
            'desk_rows_not_in_bitrix'     => $missing,
            'bitrix_tasks_not_in_desk'    => $notInDesk,
            'tasks_with_any_difference'   => $anyDiff,
            'of_which_also_edited_in_desk' => $anyDiffEdited,
            'bitrix_status_4_supposedly_completed' => $status4,
            'status_differences_bitrix_to_desk' => $statusPairs,
            'per_field'                   => $diff,
            'desk_only_tasks_created_here' => Task::whereNull('bitrix_id')->count(),
        ], JSON_PRETTY_PRINT));
        return 0;
    }
}
