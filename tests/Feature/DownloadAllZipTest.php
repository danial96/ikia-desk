<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadAllZipTest extends TestCase
{
    use RefreshDatabase;

    private function stash(string $rel, string $body): void
    {
        $abs = Uploads::path($rel);
        @mkdir(dirname($abs), 0777, true);
        file_put_contents($abs, $body);
    }

    public function test_task_files_and_message_attachments_download_as_one_zip(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $task  = Task::create(['title' => 'Zipped', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'new']);
        $this->stash('bitrix/tatt_1_a.txt', 'AAA');
        $this->stash('bitrix/tatt_2_a.txt', 'BBB');   // same display name as the first → must not overwrite it
        foreach ([[1, 'tatt_1_a.txt'], [2, 'tatt_2_a.txt']] as [$i, $f]) {
            TaskFile::create(['task_id' => $task->id, 'uploaded_by' => $admin->id, 'name' => 'report.txt', 'size' => 3, 'disk_path' => 'uploads/bitrix/' . $f, 'is_task_attachment' => true]);
        }

        $res = $this->actingAs($admin)->get(route('api.local.task.files.zip', $task->id))->assertOk();
        $this->assertStringContainsString('zip', (string) $res->headers->get('content-type'));
        $tmp = tempnam(sys_get_temp_dir(), 'zt'); file_put_contents($tmp, $res->baseResponse->getFile()->getContent());
        $zip = new \ZipArchive(); $this->assertTrue($zip->open($tmp) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) $names[] = $zip->getNameIndex($i);
        sort($names);
        $this->assertSame(['report (1).txt', 'report.txt'], $names);

        // arbitrary uploaded files (a comment's attachments) — and nothing outside the uploads folder
        $this->stash('bitrix/chat/chat_9_photo.png', 'PNG');
        $r = $this->actingAs($admin)->post(route('api.download.zip'), ['urls' => ['/uploads/bitrix/chat/chat_9_photo.png', '/uploads/../../.env'], 'name' => 'pics'])->assertOk();
        $tmp2 = tempnam(sys_get_temp_dir(), 'zt'); file_put_contents($tmp2, $r->baseResponse->getFile()->getContent());
        $z2 = new \ZipArchive(); $z2->open($tmp2);
        $this->assertSame(1, $z2->numFiles);
        $this->assertSame('photo.png', $z2->getNameIndex(0));   // import prefix stripped

        $this->actingAs($admin)->post(route('api.download.zip'), ['urls' => ['/uploads/does/not/exist.png']])->assertStatus(404);

        $other = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($other)->get(route('api.local.task.files.zip', $task->id))->assertStatus(403);
    }
}
