<?php

namespace App\Console\Commands\Bitrix;

use App\Models\TaskFile;
use Illuminate\Console\Command;

class DownloadFiles extends Command
{
    protected $signature = 'bitrix:download-files
                            {--limit=0   : Max files to download (0 = all)}
                            {--max-mb=50 : Skip files larger than this many MB (0 = no limit)}
                            {--fresh     : Re-download even if disk_path already set}';

    protected $description = 'Download all Bitrix file attachments to local server storage';

    public function handle(): int
    {
        $maxBytes = (int)$this->option('max-mb') * 1024 * 1024;

        $query = TaskFile::whereNotNull('bitrix_download_url');
        if (!$this->option('fresh')) {
            $query->whereNull('disk_path');
        }
        if ($limit = (int)$this->option('limit')) {
            $query->limit($limit);
        }

        // Fetch IDs upfront to avoid offset-shifting bug when disk_path is set mid-loop
        $ids = $query->pluck('id')->toArray();
        $total = count($ids);
        $this->info("Files to download: $total");

        $dir = public_path('uploads/bitrix');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $done = $failed = $skipped = 0;
        $totalBytes = 0;

        foreach ($ids as $id) {
            $file = TaskFile::find($id);
            if (!$file) { $bar->advance(); continue; }

            try {
                if ($maxBytes > 0 && $file->size > $maxBytes) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $ext      = strtolower(pathinfo($file->name ?? '', PATHINFO_EXTENSION));
                $base     = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file->name ?? 'file', PATHINFO_FILENAME));
                $filename = $file->id . '_' . substr($base, 0, 60) . ($ext ? '.' . $ext : '');
                $savePath = $dir . '/' . $filename;

                $fp = fopen($savePath, 'wb');
                $ch = curl_init($file->bitrix_download_url);
                curl_setopt_array($ch, [
                    CURLOPT_FILE           => $fp,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 120,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0',
                ]);
                $result   = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_error($ch);
                curl_close($ch);
                fclose($fp);

                if (!$result || $curlErr || $httpCode < 200 || $httpCode >= 300) {
                    @unlink($savePath);
                    $this->newLine();
                    $this->warn("  FAIL [{$httpCode}] id={$file->id} {$file->name}: $curlErr");
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $fileSize = filesize($savePath);
                if ($maxBytes > 0 && $fileSize > $maxBytes) {
                    @unlink($savePath);
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $file->update(['disk_path' => 'uploads/bitrix/' . $filename]);
                $totalBytes += $fileSize;
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("  ERR id={$file->id}: " . $e->getMessage());
            }

            usleep(100000); // 100ms between downloads
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf(
            "Done. Downloaded: %d (%.1f MB) | Failed: %d | Skipped (too large): %d",
            $done, $totalBytes / 1024 / 1024, $failed, $skipped
        ));
        return 0;
    }
}
