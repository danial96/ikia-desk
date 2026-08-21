<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Task;
use App\Models\TaskFile;

class ImportTaskAttachments extends BitrixCommand
{
    protected $signature = 'bitrix:import-task-attachments
                            {--task-id= : Import only this local task ID}
                            {--fresh    : Re-download already downloaded files}';

    protected $description = 'Import UF_TASK_WEBDAV_FILES (direct task attachments) from Bitrix into task_files';

    private string $dir;

    public function handle(): int
    {
        $this->dir = public_path('uploads/bitrix');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }

        $query = Task::whereNotNull('bitrix_id');
        if ($taskId = $this->option('task-id')) {
            $query->where('id', (int)$taskId);
        }

        $total = $query->count();
        $this->info("Processing $total tasks for direct attachments...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = $skipped = $failed = 0;

        $query->orderBy('id')->chunk(100, function ($tasks) use (&$imported, &$skipped, &$failed, $bar) {
            foreach ($tasks as $task) {
                $resp = $this->bx('tasks.task.get', [
                    'taskId' => $task->bitrix_id,
                    'select' => ['UF_TASK_WEBDAV_FILES'],
                ]);

                $diskIds = $resp['result']['task']['ufTaskWebdavFiles'] ?? [];
                // Fallback: extract disk file IDs from description
                if (empty($diskIds) && $task->description) {
                    preg_match_all('/\[disk\s+file\s+id=n(\d+)[^\]]*\]/i', $task->description, $dm);
                    $diskIds = array_map('intval', $dm[1] ?? []);
                }
                if (empty($diskIds)) {
                    $bar->advance();
                    continue;
                }

                foreach ($diskIds as $diskId) {
                    $diskId = (int)$diskId;
                    if (!$diskId) continue;

                    // Check if already imported as task attachment
                    $existing = TaskFile::where('bitrix_file_id', $diskId)
                        ->where('task_id', $task->id)
                        ->where('is_task_attachment', true)
                        ->first();

                    if ($existing && !$this->option('fresh')) {
                        $skipped++;
                        continue;
                    }

                    // Get file metadata from Bitrix
                    $fileResp = $this->bx('disk.file.get', ['id' => $diskId]);
                    $info = $fileResp['result'] ?? null;
                    if (!$info) {
                        // ACCESS_DENIED — try to reuse an already-cached file for this disk ID
                        $cached = TaskFile::where('bitrix_file_id', $diskId)->whereNotNull('disk_path')->first();
                        if ($cached) {
                            TaskFile::updateOrCreate(
                                ['bitrix_file_id' => $diskId, 'task_id' => $task->id, 'is_task_attachment' => true],
                                ['uploaded_by' => $task->created_by ?? 1, 'name' => $cached->name,
                                 'size' => $cached->size, 'disk_path' => $cached->disk_path, 'is_task_attachment' => true]
                            );
                            $imported++;
                        } else {
                            $failed++;
                        }
                        continue;
                    }

                    $downloadUrl = $info['DOWNLOAD_URL'] ?? null;
                    $name        = $info['NAME'] ?? ('file_' . $diskId);
                    if (!$downloadUrl) { $failed++; continue; }

                    // Download the file
                    $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $base     = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($name, PATHINFO_FILENAME));
                    $filename = 'tdirect_' . $diskId . '_' . substr($base, 0, 60) . ($ext ? '.' . $ext : '');
                    $savePath = $this->dir . '/' . $filename;

                    if (!$this->option('fresh') && file_exists($savePath) && filesize($savePath) > 0) {
                        $diskPath = 'uploads/bitrix/' . $filename;
                    } else {
                        $fp = fopen($savePath, 'wb');
                        if (!$fp) { $failed++; continue; }

                        $ch = curl_init($downloadUrl);
                        curl_setopt_array($ch, [
                            CURLOPT_FILE           => $fp,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_TIMEOUT        => 120,
                            CURLOPT_USERAGENT      => 'Mozilla/5.0',
                        ]);
                        $ok   = curl_exec($ch);
                        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        fclose($fp);

                        if (!$ok || $code < 200 || $code >= 300 || filesize($savePath) === 0) {
                            @unlink($savePath);
                            $failed++;
                            continue;
                        }

                        $diskPath = 'uploads/bitrix/' . $filename;
                    }

                    TaskFile::updateOrCreate(
                        ['bitrix_file_id' => $diskId, 'task_id' => $task->id, 'is_task_attachment' => true],
                        [
                            'uploaded_by'       => $task->created_by ?? 1,
                            'name'              => $name,
                            'size'              => filesize($savePath),
                            'disk_path'         => $diskPath,
                            'is_task_attachment'=> true,
                        ]
                    );
                    $imported++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Imported: $imported | Skipped: $skipped | Failed: $failed");
        return 0;
    }
}
