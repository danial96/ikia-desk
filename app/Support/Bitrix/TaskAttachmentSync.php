<?php

namespace App\Support\Bitrix;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;

/**
 * Imports a Bitrix task's DIRECT attachments correctly.
 *
 * Bitrix keeps them in UF_TASK_WEBDAV_FILES as *attached-object* IDs (disk.attachedObject),
 * which is NOT the same ID space as disk file/object IDs (disk.file). The old importer passed
 * those IDs straight to disk.file.get and so stored whatever unrelated file happened to own
 * that number. The correct path is:
 *
 *   attached-object id --disk.attachedObject.get--> OBJECT_ID --disk.file.get--> DOWNLOAD_URL
 *
 * The Bitrix API and the downloader are injected so the logic can be unit-tested.
 */
class TaskAttachmentSync
{
    /**
     * @param \Closure $api      fn(string $method, array $params): ?array — the method's `result`, or null
     * @param \Closure $download fn(string $url, string $absolutePath): bool
     * @param string   $dir      absolute directory downloaded files are written to
     */
    public function __construct(
        private \Closure $api,
        private \Closure $download,
        private string $dir,
    ) {}

    /**
     * @param  int[] $attachedIds the task's UF_TASK_WEBDAV_FILES values
     * @return array{added:int,kept:int,failed:int,removed:int}
     */
    public function sync(Task $task, array $attachedIds): array
    {
        $stats = ['added' => 0, 'kept' => 0, 'failed' => 0, 'removed' => 0];
        $attachedIds = array_values(array_unique(array_filter(array_map('intval', $attachedIds))));

        foreach ($attachedIds as $aid) {
            $exists = TaskFile::where('task_id', $task->id)->where('bitrix_attached_id', $aid)->exists();
            if ($exists)                         { $stats['kept']++;   continue; }
            if ($this->import($task, $aid))      { $stats['added']++;  }
            else                                 { $stats['failed']++; }
        }

        // Legacy rows for this task: the old importer stored the attached-object id in
        // bitrix_file_id (and has no bitrix_attached_id). Those point at unrelated files —
        // remove them (a missing file is better than someone else's file).
        if ($attachedIds) {
            $stats['removed'] = TaskFile::where('task_id', $task->id)
                ->where('is_task_attachment', true)
                ->whereNull('bitrix_attached_id')
                ->whereIn('bitrix_file_id', $attachedIds)
                ->delete();
        }

        return $stats;
    }

    /** On-disk name for a task attachment: keyed by the disk OBJECT id so it can never clash with legacy tdirect_ files. */
    public static function filenameFor(int $objectId, string $name): string
    {
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($name, PATHINFO_FILENAME));
        return 'tatt_' . $objectId . '_' . substr($base, 0, 60) . ($ext ? '.' . $ext : '');
    }

    private function import(Task $task, int $attachedId): bool
    {
        $ao = ($this->api)('disk.attachedObject.get', ['id' => $attachedId]);
        if (!$ao || empty($ao['OBJECT_ID'])) return false;

        // Never attach a file that Bitrix says belongs to some other entity.
        if (isset($ao['ENTITY_ID']) && (int)$ao['ENTITY_ID'] !== (int)$task->bitrix_id) return false;

        $objectId = (int)$ao['OBJECT_ID'];
        $name     = (string)($ao['NAME'] ?? ('file_' . $objectId));
        $filename = self::filenameFor($objectId, $name);
        $abs      = rtrim($this->dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        $diskPath = 'uploads/bitrix/' . $filename;
        $size     = null;

        if (is_file($abs) && filesize($abs) > 0) {
            $size = filesize($abs);
        } else {
            $file = ($this->api)('disk.file.get', ['id' => $objectId]);
            $url  = $file['DOWNLOAD_URL'] ?? null;
            $ok   = $url && ($this->download)($url, $abs) && is_file($abs) && filesize($abs) > 0;

            if ($ok) {
                $size = filesize($abs);
            } else {
                @unlink($abs);
                // Reuse an already-downloaded copy of the SAME disk object (e.g. also posted in
                // chat). Legacy tdirect_ rows are excluded — those are the wrong files.
                $cached = TaskFile::where('bitrix_file_id', $objectId)
                    ->whereNotNull('disk_path')
                    ->where('disk_path', 'not like', 'uploads/bitrix/tdirect_%')
                    ->first();
                if (!$cached) return false;
                $diskPath = $cached->disk_path;
                $size     = $cached->size;
            }
        }

        $uploader = User::where('bitrix_id', (int)($ao['CREATED_BY'] ?? 0))->value('id')
            ?? $task->created_by
            ?? 1;

        TaskFile::create([
            'task_id'            => $task->id,
            'uploaded_by'        => $uploader,
            'bitrix_file_id'     => $objectId,
            'bitrix_attached_id' => $attachedId,
            'is_task_attachment' => true,
            'name'               => $name,
            'size'               => $size ?: (int)($ao['SIZE'] ?? 0),
            'disk_path'          => $diskPath,
        ]);

        return true;
    }
}
