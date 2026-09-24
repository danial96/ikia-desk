<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Task;
use App\Models\TaskFile;
use App\Support\Bitrix\TaskAttachmentSync;
use App\Support\Uploads;

class ImportTaskAttachments extends BitrixCommand
{
    protected $signature = 'bitrix:import-task-attachments
                            {--task-id=   : Only this LOCAL task id}
                            {--limit=0    : Max tasks to process (0 = all)}
                            {--oldest-first : Walk tasks oldest-first (default: newest-first)}
                            {--chunk=25   : Tasks per Bitrix batch request (max 50)}';

    protected $description = 'Import direct task attachments (UF_TASK_WEBDAV_FILES). Resolves attached-object → disk object correctly and replaces legacy wrong rows.';

    /** Memoised API results for the current chunk: "method|params" => result. */
    private array $cache = [];

    public function handle(): int
    {
        $dir = Uploads::path('bitrix');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $sync = new TaskAttachmentSync(
            fn(string $m, array $p) => $this->cached($m, $p),
            fn(string $url, string $dest) => $this->downloadTo($url, $dest),
            $dir,
        );

        $query = Task::whereNotNull('bitrix_id');
        if ($id = $this->option('task-id')) $query->where('id', (int)$id);

        $limit     = (int)$this->option('limit');
        $chunkSize = max(1, min(50, (int)$this->option('chunk')));
        $total     = $limit > 0 ? min($limit, (clone $query)->count()) : (clone $query)->count();
        $this->info("Syncing direct attachments for $total task(s), newest-first, $chunkSize per batch...");

        $totals = ['tasks' => 0, 'added' => 0, 'kept' => 0, 'failed' => 0, 'removed' => 0, 'skipped' => 0];

        $handler = function ($tasks) use ($sync, &$totals, $limit, $total) {
            $this->cache = [];

            // 1) Which attached-object ids does each task have? (one batch request per chunk)
            $calls = [];
            foreach ($tasks as $t) {
                $calls['t' . $t->id] = ['tasks.task.get', ['taskId' => $t->bitrix_id, 'select' => ['ID', 'UF_TASK_WEBDAV_FILES']]];
            }
            $res = $this->batch($calls);

            $attached = [];
            foreach ($tasks as $t) {
                $r = $res['t' . $t->id] ?? null;
                if (!is_array($r) || !isset($r['task'])) { $totals['skipped']++; continue; }   // Bitrix didn't answer: leave this task untouched
                $ids = $r['task']['ufTaskWebdavFiles'] ?? [];
                $attached[$t->id] = is_array($ids) ? array_map('intval', $ids) : [];
            }

            // 2) Prefetch attached-object metadata, then file metadata, for what's actually missing
            $needAttached = [];
            foreach ($attached as $tid => $ids) {
                foreach ($ids as $aid) {
                    if (!TaskFile::where('task_id', $tid)->where('bitrix_attached_id', $aid)->exists()) $needAttached[$aid] = true;
                }
            }
            $this->prefetch('disk.attachedObject.get', array_keys($needAttached));

            $needObjects = [];
            foreach (array_keys($needAttached) as $aid) {
                $ao = $this->cache['disk.attachedObject.get|' . json_encode(['id' => $aid])] ?? null;
                if (!is_array($ao) || empty($ao['OBJECT_ID'])) continue;
                $file = rtrim(Uploads::path('bitrix'), '/\\') . DIRECTORY_SEPARATOR
                      . TaskAttachmentSync::filenameFor((int)$ao['OBJECT_ID'], (string)($ao['NAME'] ?? ''));
                if (!(is_file($file) && filesize($file) > 0)) $needObjects[(int)$ao['OBJECT_ID']] = true;
            }
            $this->prefetch('disk.file.get', array_keys($needObjects));

            // 3) Apply
            foreach ($tasks as $t) {
                if (!isset($attached[$t->id])) continue;
                $s = $sync->sync($t, $attached[$t->id]);
                $totals['tasks']++;
                foreach (['added', 'kept', 'failed', 'removed'] as $k) $totals[$k] += $s[$k];
            }

            $this->line(sprintf('  %d/%d tasks | +%d new, %d already ok, %d failed, %d legacy wrong rows removed, %d skipped',
                $totals['tasks'] + $totals['skipped'], $total, $totals['added'], $totals['kept'], $totals['failed'], $totals['removed'], $totals['skipped']));

            if ($limit > 0 && ($totals['tasks'] + $totals['skipped']) >= $limit) return false;   // stop chunking
            return true;
        };

        $this->option('oldest-first')
            ? $query->chunkById($chunkSize, $handler)
            : $query->chunkByIdDesc($chunkSize, $handler);

        $this->info(sprintf('Done. tasks=%d added=%d already-ok=%d failed=%d legacy-removed=%d skipped=%d',
            $totals['tasks'], $totals['added'], $totals['kept'], $totals['failed'], $totals['removed'], $totals['skipped']));
        return 0;
    }

    /** Serve from the prefetch cache; fall back to a single call. Returns the method's `result`. */
    private function cached(string $method, array $params): ?array
    {
        $key = $method . '|' . json_encode($params);
        if (array_key_exists($key, $this->cache)) return $this->cache[$key];
        $r = $this->bx($method, $params);
        return $this->cache[$key] = (is_array($r['result'] ?? null) ? $r['result'] : null);
    }

    /** Fetch many `id` lookups with as few Bitrix requests as possible (batch = up to 50 calls per request). */
    private function prefetch(string $method, array $ids): void
    {
        $calls = [];
        foreach ($ids as $id) $calls['i' . $id] = [$method, ['id' => (int)$id]];
        if (!$calls) return;
        foreach ($this->batch($calls) as $key => $result) {
            $id = (int)substr($key, 1);
            $this->cache[$method . '|' . json_encode(['id' => $id])] = is_array($result) ? $result : null;
        }
    }

    /**
     * Run many method calls through Bitrix's `batch` endpoint (50 per HTTP request).
     * @param  array<string,array{0:string,1:array}> $calls key => [method, params]
     * @return array<string,mixed> key => result (null when that call failed)
     */
    private function batch(array $calls): array
    {
        $out = [];
        foreach (array_chunk($calls, 50, true) as $group) {
            $cmd = [];
            foreach ($group as $key => [$method, $params]) $cmd[$key] = $method . '?' . $this->buildQuery($params);
            $resp = $this->postBatch($cmd);
            $results = $resp['result']['result'] ?? [];
            foreach ($group as $key => $_) $out[$key] = $results[$key] ?? null;
        }
        return $out;
    }

    private function postBatch(array $cmd): ?array
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            usleep(600000);   // stay under Bitrix's ~2 requests/second
            $ch = curl_init($this->webhook . 'batch.json');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query(['halt' => 0, 'cmd' => $cmd]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 120,
            ]);
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            $data = $raw ? json_decode($raw, true) : null;
            if ($err || !is_array($data)) { $this->warn("  batch request failed: $err"); sleep(5); continue; }

            $e = $data['error'] ?? null;
            if (in_array($e, ['QUERY_LIMIT_EXCEEDED', 'OPERATION_TIME_LIMIT'], true)) { sleep(30); continue; }
            if ($e) { $this->warn("  Bitrix batch error [$e]: " . ($data['error_description'] ?? '')); return null; }
            return $data;
        }
        return null;
    }

    private function downloadTo(string $url, string $dest): bool
    {
        $fp = fopen($dest, 'wb');
        if (!$fp) return false;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        $ok   = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $code < 200 || $code >= 300 || filesize($dest) === 0) { @unlink($dest); return false; }
        return true;
    }
}
