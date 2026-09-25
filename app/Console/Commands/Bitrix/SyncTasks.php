<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Makes the desk copy of every Bitrix task match Bitrix exactly — BITRIX WINS.
 *
 *  - per task: title, description, status, priority, deadline, closed date, creator, assignee, project
 *  - participants and observers are replaced by Bitrix's lists (people removed in Bitrix are removed here too)
 *  - the desk's "Active" time (updated_at) is set to Bitrix's last-change time
 *  - tasks that no longer exist in Bitrix are moved to the Trash (never hard-deleted)
 *  - desk-only tasks (created here, no bitrix_id) and everything that only exists in the desk
 *    (comments written here, local files) are left alone
 *
 * Every value it overwrites is written to storage/logs/task-sync-*.jsonl (old → new, and whether the
 * task had also been edited inside the desk) so nothing is lost silently. --dry-run changes nothing.
 */
class SyncTasks extends BitrixCommand
{
    protected $signature = 'bitrix:sync-tasks {--dry-run : Only report and log, change nothing} {--webhook= : use this webhook instead of BITRIX_WEBHOOK} {--only= : comma separated Bitrix task ids}';
    protected $description = 'Make the desk tasks match Bitrix exactly (Bitrix wins); logs every overwritten value';

    public function handle(): int
    {
        if ($w = $this->option('webhook')) $this->webhook = rtrim($w, '/') . '/';
        $dry = (bool) $this->option('dry-run');

        $users    = User::whereNotNull('bitrix_id')->pluck('id', 'bitrix_id')->all();
        $projects = Project::whereNotNull('bitrix_id')->pluck('id', 'bitrix_id')->all();
        $statusMap   = ['1' => 'new', '2' => 'pending', '3' => 'in_progress', '4' => 'reviewing', '5' => 'completed', '6' => 'paused'];
        $priorityMap = ['0' => 'low', '1' => 'medium', '2' => 'urgent'];
        $tz = new \DateTimeZone(config('app.timezone'));
        $parse = function ($v) use ($tz) {
            if (!$v || $v === '0000-00-00T00:00:00+00:00') return null;
            try { return (new \DateTime($v))->setTimezone($tz)->format('Y-m-d H:i:s'); } catch (\Throwable) { return null; }
        };

        $only = array_filter(array_map('intval', explode(',', (string) $this->option('only'))));
        $this->info('Reading every task from Bitrix…');
        $remote = [];
        foreach ($this->bxAll('tasks.task.list', [
            'order'  => ['ID' => 'ASC'],
            'select' => ['ID', 'TITLE', 'DESCRIPTION', 'STATUS', 'PRIORITY', 'DEADLINE', 'CREATED_BY', 'RESPONSIBLE_ID', 'GROUP_ID', 'ACCOMPLICES', 'AUDITORS', 'CHANGED_DATE', 'CLOSED_DATE'],
        ]) as $t) {
            $id = (int)($t['id'] ?? $t['ID'] ?? 0);
            if ($id && (!$only || in_array($id, $only, true))) $remote[$id] = $t;
        }
        $this->info('  ' . count($remote) . ' tasks in Bitrix');
        if (count($remote) < 100 && !$only) { $this->error('Bitrix returned suspiciously few tasks — aborting so nothing is trashed by mistake.'); return 1; }

        $edited    = DB::table('task_activities')->distinct()->pluck('task_id')->flip()->all();
        $memberSet = DB::table('task_members')->get()->groupBy('task_id')->map(fn($g) => $g->pluck('user_id')->map(fn($x) => (int)$x)->sort()->values()->all())->all();
        $observSet = DB::table('task_observers')->get()->groupBy('task_id')->map(fn($g) => $g->pluck('user_id')->map(fn($x) => (int)$x)->sort()->values()->all())->all();

        $logPath = storage_path('logs/task-sync-' . ($dry ? 'dry-' : '') . date('Ymd-His') . '.jsonl');
        $log = fopen($logPath, 'w');
        $stats = ['tasks_checked' => 0, 'tasks_changed' => 0, 'fields_changed' => 0, 'of_which_desk_had_edited' => 0, 'trashed_missing_in_bitrix' => 0, 'per_field' => []];

        Task::withTrashed()->whereNotNull('bitrix_id')->when($only, fn($q) => $q->whereIn('bitrix_id', $only))->chunkById(300, function ($tasks) use (&$stats, $remote, $users, $projects, $statusMap, $priorityMap, $parse, $edited, &$memberSet, &$observSet, $dry, $log) {
            foreach ($tasks as $task) {
                $r = $remote[(int)$task->bitrix_id] ?? null;

                // gone from Bitrix → Trash (recoverable), unless it is already there
                if (!$r) {
                    if (!$task->trashed()) {
                        $stats['trashed_missing_in_bitrix']++;
                        fwrite($log, json_encode(['task' => $task->id, 'bitrix' => $task->bitrix_id, 'field' => 'deleted', 'old' => null, 'new' => 'moved to trash (not in Bitrix)', 'desk_edited' => isset($edited[$task->id])]) . "\n");
                        if (!$dry) { $task->forceFill(['deleted_by' => null])->save(); $task->delete(); }
                    }
                    continue;
                }
                if ($task->trashed()) continue;   // deleted in the desk on purpose — leave it in the Trash
                $stats['tasks_checked']++;

                $want = [
                    'title'       => trim((string)($r['title'] ?? '')) !== '' ? (string)$r['title'] : $task->title,
                    'description' => $r['description'] ?? null,
                    'status'      => $statusMap[(string)($r['status'] ?? '')] ?? $task->status,
                    'priority'    => $priorityMap[(string)($r['priority'] ?? '1')] ?? 'medium',
                    'deadline'    => $parse($r['deadline'] ?? null),
                    'closed_date' => $parse($r['closedDate'] ?? null),
                ];
                $cb = $users[(int)($r['createdBy'] ?? 0)] ?? null;        if ($cb) $want['created_by'] = $cb;
                $as = $users[(int)($r['responsibleId'] ?? 0)] ?? null;    $want['assigned_to'] = $as;
                $g  = (int)($r['groupId'] ?? 0);
                if (!$g) $want['project_id'] = null; elseif (isset($projects[$g])) $want['project_id'] = $projects[$g];

                $row = DB::table('tasks')->where('id', $task->id)->first();
                $changes = [];
                foreach ($want as $field => $new) {
                    $old = $row->$field;
                    $norm = fn($v) => trim(str_replace("
", "
", (string)$v));
                    $same = $field === 'deadline' || $field === 'closed_date'
                        ? ($old ? date('Y-m-d H:i', strtotime((string)$old)) : null) === ($new ? date('Y-m-d H:i', strtotime($new)) : null)
                        : ($field === 'description' ? $norm($old) === $norm($new) : (string)$old === (string)$new);
                    if (!$same) { $changes[$field] = ['old' => $old, 'new' => $new]; }
                }

                $rm = collect((array)($r['accomplices'] ?? []))->map(fn($b) => $users[(int)$b] ?? null)->filter()->map(fn($x) => (int)$x)->sort()->values()->all();
                $ro = collect((array)($r['auditors'] ?? []))->map(fn($b) => $users[(int)$b] ?? null)->filter()->map(fn($x) => (int)$x)->sort()->values()->all();
                $haveM = $memberSet[$task->id] ?? []; $haveO = $observSet[$task->id] ?? [];
                if ($rm !== $haveM) $changes['participants'] = ['old' => $haveM, 'new' => $rm];
                if ($ro !== $haveO) $changes['observers']    = ['old' => $haveO, 'new' => $ro];

                $activity = $parse($r['changedDate'] ?? null);
                $wasEdited = isset($edited[$task->id]);
                if ($changes) {
                    $stats['tasks_changed']++;
                    foreach ($changes as $f => $c) {
                        $stats['fields_changed']++;
                        $stats['per_field'][$f] = ($stats['per_field'][$f] ?? 0) + 1;
                        if ($wasEdited) $stats['of_which_desk_had_edited']++;
                        fwrite($log, json_encode(['task' => $task->id, 'bitrix' => $task->bitrix_id, 'field' => $f, 'old' => $c['old'], 'new' => $c['new'], 'desk_edited' => $wasEdited]) . "\n");
                    }
                }

                if ($dry) continue;
                $update = [];
                foreach ($changes as $f => $c) if (!in_array($f, ['participants', 'observers'], true)) $update[$f] = $c['new'];
                if ($update) { $task->forceFill($update)->save(); }          // fires the model events (board cache)
                if (isset($changes['participants'])) $task->members()->sync($rm);
                if (isset($changes['observers']))    $task->observers()->sync($ro);
                // "Active" = Bitrix's own last-change time
                if ($activity) DB::table('tasks')->where('id', $task->id)->update(['updated_at' => $activity]);
            }
        });

        fclose($log);
        $this->newLine();
        $this->info(($dry ? '[DRY RUN — nothing changed] ' : '') . json_encode($stats, JSON_PRETTY_PRINT));
        $this->info('Log of every overwritten value: ' . $logPath);
        return 0;
    }
}
