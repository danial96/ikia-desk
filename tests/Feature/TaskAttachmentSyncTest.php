<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use App\Support\Bitrix\TaskAttachmentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bitrix's UF_TASK_WEBDAV_FILES holds ATTACHED-OBJECT ids, not disk file ids. The old importer
 * fed them to disk.file.get and stored an unrelated file (e.g. task 23269 got "R1 (145).jpg"
 * instead of "Time for You-08 (1).jpg"). These tests use those real ids.
 */
class TaskAttachmentSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'att_' . uniqid();
        mkdir($this->dir, 0777, true);
        $this->calls = [];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function task(int $bitrixId = 23269): Task
    {
        $u = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        return Task::create(['title' => 'Landing page', 'created_by' => $u->id, 'priority' => 'medium', 'status' => 'in_progress', 'bitrix_id' => $bitrixId]);
    }

    /** Fake Bitrix: attached object 105465 -> real file 192065; but disk object 105465 is a DIFFERENT (wrong) file. */
    private function sync(array $overrides = [], bool $downloadOk = true): TaskAttachmentSync
    {
        $api = function (string $method, array $params) use ($overrides) {
            $this->calls[] = [$method, $params['id'] ?? null];
            if (isset($overrides[$method . ':' . $params['id']])) return $overrides[$method . ':' . $params['id']];
            return match ($method . ':' . $params['id']) {
                'disk.attachedObject.get:105465' => ['ID' => '105465', 'OBJECT_ID' => '192065', 'ENTITY_TYPE' => 'tasks_task', 'ENTITY_ID' => '23269', 'NAME' => 'Time for You-08 (1).jpg', 'SIZE' => '154236', 'CREATED_BY' => '0'],
                'disk.file.get:192065'           => ['ID' => '192065', 'NAME' => 'Time for You-08 (1).jpg', 'DOWNLOAD_URL' => 'https://bx.test/download/192065'],
                'disk.file.get:105465'           => ['ID' => '105465', 'NAME' => 'R1 (145).jpg', 'DOWNLOAD_URL' => 'https://bx.test/download/105465'], // the WRONG file
                default                          => null,
            };
        };
        $download = function (string $url, string $dest) use ($downloadOk) {
            if (!$downloadOk) return false;
            file_put_contents($dest, 'bytes-of:' . $url);
            return true;
        };
        return new TaskAttachmentSync($api, $download, $this->dir);
    }

    public function test_resolves_attached_object_to_the_real_file_not_the_same_numbered_disk_file(): void
    {
        $task  = $this->task();
        $stats = $this->sync()->sync($task, [105465]);

        $this->assertSame(1, $stats['added']);
        $f = TaskFile::where('task_id', $task->id)->sole();
        $this->assertSame('Time for You-08 (1).jpg', $f->name);
        $this->assertSame(192065, (int)$f->bitrix_file_id);      // disk OBJECT id
        $this->assertSame(105465, (int)$f->bitrix_attached_id);  // the attached-object id we started from
        $this->assertTrue((bool)$f->is_task_attachment);
        $this->assertSame('uploads/bitrix/tatt_192065_Time_for_You-08__1_.jpg', $f->disk_path);
        $this->assertSame('bytes-of:https://bx.test/download/192065', file_get_contents($this->dir . '/tatt_192065_Time_for_You-08__1_.jpg'));

        // The bug: disk.file.get must NEVER be called with the attached-object id.
        $this->assertNotContains(['disk.file.get', 105465], $this->calls);
    }

    public function test_legacy_wrong_row_is_replaced(): void
    {
        $task = $this->task();
        $legacy = TaskFile::create([
            'task_id' => $task->id, 'uploaded_by' => $task->created_by, 'bitrix_file_id' => 105465, 'is_task_attachment' => true,
            'name' => 'R1 (145).jpg', 'size' => 4540504, 'disk_path' => 'uploads/bitrix/tdirect_105465_R1__145_.jpg',
        ]);

        $stats = $this->sync()->sync($task, [105465]);

        $this->assertSame(['added' => 1, 'kept' => 0, 'failed' => 0, 'removed' => 1], $stats);
        $this->assertNull(TaskFile::find($legacy->id));
        $this->assertSame(['Time for You-08 (1).jpg'], TaskFile::where('task_id', $task->id)->pluck('name')->all());
    }

    public function test_second_run_is_idempotent(): void
    {
        $task = $this->task();
        $sync = $this->sync();
        $sync->sync($task, [105465]);
        $stats = $sync->sync($task, [105465]);

        $this->assertSame(['added' => 0, 'kept' => 1, 'failed' => 0, 'removed' => 0], $stats);
        $this->assertSame(1, TaskFile::where('task_id', $task->id)->count());
    }

    public function test_file_that_bitrix_says_belongs_to_another_entity_is_never_attached(): void
    {
        $task  = $this->task();
        $stats = $this->sync(['disk.attachedObject.get:105465' => ['OBJECT_ID' => '192065', 'ENTITY_ID' => '99999', 'NAME' => 'x.jpg']])->sync($task, [105465]);

        $this->assertSame(1, $stats['failed']);
        $this->assertSame(0, TaskFile::where('task_id', $task->id)->count());
    }

    public function test_wrong_legacy_row_is_still_removed_when_the_real_file_cannot_be_fetched(): void
    {
        $task = $this->task();
        TaskFile::create(['task_id' => $task->id, 'uploaded_by' => $task->created_by, 'bitrix_file_id' => 105465, 'is_task_attachment' => true,
            'name' => 'R1 (145).jpg', 'disk_path' => 'uploads/bitrix/tdirect_105465_R1__145_.jpg']);

        $stats = $this->sync([], false)->sync($task, [105465]);   // download fails, nothing cached

        $this->assertSame(1, $stats['failed']);
        $this->assertSame(1, $stats['removed']);
        $this->assertSame(0, TaskFile::where('task_id', $task->id)->count());   // missing beats someone else's file
    }

    public function test_reuses_an_already_downloaded_copy_of_the_same_disk_object_but_never_a_legacy_tdirect_file(): void
    {
        $task = $this->task();
        // e.g. the same file was also posted in chat and is already on disk (disk-object namespace).
        TaskFile::create(['task_id' => $task->id, 'uploaded_by' => $task->created_by, 'bitrix_file_id' => 192065,
            'name' => 'Time for You-08 (1).jpg', 'size' => 154236, 'disk_path' => 'uploads/bitrix/28516_time.jpg']);
        // a legacy wrong row that happens to share the number must not be reused
        TaskFile::create(['task_id' => $task->id, 'uploaded_by' => $task->created_by, 'bitrix_file_id' => 192065,
            'is_task_attachment' => true, 'name' => 'WRONG.jpg', 'disk_path' => 'uploads/bitrix/tdirect_192065_WRONG.jpg']);

        $this->sync([], false)->sync($task, [105465]);

        $new = TaskFile::where('bitrix_attached_id', 105465)->sole();
        $this->assertSame('uploads/bitrix/28516_time.jpg', $new->disk_path);
    }

    public function test_description_derived_rows_using_real_object_ids_are_preserved(): void
    {
        $task = $this->task();
        // Created by the old importer's description fallback ([disk file id=n555]) — a genuine object id.
        $keep = TaskFile::create(['task_id' => $task->id, 'uploaded_by' => $task->created_by, 'bitrix_file_id' => 555,
            'is_task_attachment' => true, 'name' => 'from-description.png', 'disk_path' => 'uploads/bitrix/tdirect_555_from-description.png']);

        $this->sync()->sync($task, [105465]);

        $this->assertNotNull(TaskFile::find($keep->id));
    }
}
