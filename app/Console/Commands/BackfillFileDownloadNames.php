<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Console\Command;

/**
 * One-time backfill: [file name="X"]url[/file] tags written before the download-name fix have
 * URLs pointing at the bare generated storage id, with no way to recover the original name.
 * This appends ?name=<original> to those URLs, same as new uploads already get. Idempotent —
 * skips anything that already has ?name= in its URL, so it's safe to re-run.
 */
class BackfillFileDownloadNames extends Command
{
    protected $signature = 'files:backfill-names {--dry-run}';
    protected $description = 'Append ?name=<original> to old [file name="..."]url[/file] tags in messages, task comments and task descriptions';

    private const PATTERN = '/\[file name="([^"]*)"\](https?:\/\/\S*?\/uploads\/up_[A-Za-z0-9]+\.[A-Za-z0-9]+(?:\?\S*)?)\[\/file\]/';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $targets = [
            [Message::class, 'content'],
            [TaskComment::class, 'content'],
            [Task::class, 'description'],
        ];

        $grandRows = 0;
        $grandTags = 0;

        foreach ($targets as [$modelClass, $col]) {
            $rowsChanged = 0;
            $tagsChanged = 0;

            $modelClass::withTrashed()
                ->whereNotNull($col)
                ->where($col, 'like', '%[file name=%/uploads/up_%')
                ->chunkById(200, function ($rows) use ($col, &$rowsChanged, &$tagsChanged, $dryRun) {
                    foreach ($rows as $row) {
                        $count = 0;
                        $new = $this->fixContent($row->{$col}, $count);
                        if ($count > 0) {
                            $rowsChanged++;
                            $tagsChanged += $count;
                            if (!$dryRun) {
                                $row->{$col} = $new;
                                $row->timestamps = false;
                                $row->save();
                            }
                        }
                    }
                });

            $this->info(sprintf('%s.%s: %d row(s), %d tag(s) %s', class_basename($modelClass), $col, $rowsChanged, $tagsChanged, $dryRun ? '(dry run)' : 'fixed'));
            $grandRows += $rowsChanged;
            $grandTags += $tagsChanged;
        }

        $this->line('');
        $this->info("TOTAL: {$grandRows} row(s), {$grandTags} tag(s) " . ($dryRun ? 'would be fixed (dry run, nothing written)' : 'fixed'));

        return self::SUCCESS;
    }

    private function fixContent(string $content, int &$count): string
    {
        return preg_replace_callback(self::PATTERN, function ($m) use (&$count) {
            [$full, $name, $url] = $m;
            if (str_contains($url, '?name=')) return $full;
            $count++;
            $sep = str_contains($url, '?') ? '&' : '?';
            return '[file name="' . $name . '"]' . $url . $sep . 'name=' . rawurlencode($name) . '[/file]';
        }, $content);
    }
}
