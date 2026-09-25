<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair for two things the original import got wrong — WITHOUT re-importing the whole
 * task (a full import would overwrite anything employees have edited in the desk since):
 *
 *  1. created_at: it isn't mass-assignable, so every imported task showed the import time as
 *     "Created" instead of Bitrix's real creation time.
 *  2. Bitrix "Pending" (status 2) had been folded into "in_progress". Restore it to "pending" —
 *     but only for tasks nobody has changed the status of in the desk (no local status activity).
 */
class FixCreatedAndStatus extends BitrixCommand
{
    protected $signature = 'bitrix:fix-created-status {--dry-run : Only report what would change}';
    protected $description = 'Set real Bitrix created dates and restore the Pending status on imported tasks (touches nothing else)';

    public function handle(): int
    {
        $dry = (bool)$this->option('dry-run');
        $this->info('Reading created date + status for every task from Bitrix...');

        $remote = [];
        foreach ($this->bxAll('tasks.task.list', ['select' => ['ID', 'CREATED_DATE', 'STATUS'], 'order' => ['ID' => 'ASC']]) as $t) {
            $remote[(int)($t['id'] ?? $t['ID'] ?? 0)] = $t;
        }
        $this->info('  got ' . count($remote) . ' tasks');

        // tasks whose status was changed by a person in the desk (imports never log activities)
        $touchedStatus = DB::table('task_activities')->where('field', 'status')->distinct()->pluck('task_id')->flip()->all();

        $tz = new \DateTimeZone(config('app.timezone'));
        $stats = ['created_fixed' => 0, 'pending_set' => 0, 'pending_skipped_edited' => 0, 'not_in_bitrix' => 0];

        Task::withTrashed()->whereNotNull('bitrix_id')->select('id', 'bitrix_id', 'status', 'created_at')
            ->chunkById(500, function ($tasks) use ($remote, $tz, $touchedStatus, $dry, &$stats) {
                foreach ($tasks as $task) {
                    $r = $remote[(int)$task->bitrix_id] ?? null;
                    if (!$r) { $stats['not_in_bitrix']++; continue; }

                    $updates = [];

                    $created = $r['createdDate'] ?? $r['CREATED_DATE'] ?? null;
                    if ($created) {
                        try { $c = (new \DateTime($created))->setTimezone($tz)->format('Y-m-d H:i:s'); } catch (\Throwable) { $c = null; }
                        if ($c && $task->created_at?->format('Y-m-d H:i:s') !== $c) { $updates['created_at'] = $c; $stats['created_fixed']++; }
                    }

                    if ((string)($r['status'] ?? $r['STATUS'] ?? '') === '2' && $task->status === 'in_progress') {
                        if (isset($touchedStatus[$task->id])) { $stats['pending_skipped_edited']++; }
                        else { $updates['status'] = 'pending'; $updates['updated_at'] = now(); $stats['pending_set']++; }
                    }

                    if ($updates && !$dry) DB::table('tasks')->where('id', $task->id)->update($updates);
                }
            });

        $this->info(($dry ? '[dry-run] would change' : 'Changed') . ': ' . json_encode($stats));
        return 0;
    }
}
