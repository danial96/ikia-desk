<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAttachmentGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_images_in_a_tasks_file_list_open_in_one_gallery_with_next_and_previous(): void
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        // all of the task's image files are registered as ONE gallery, and each tile opens the viewer at its own position
        $this->assertStringContainsString('const imgFiles = localFiles.filter(f => isImgF(f) && f.downloadUrl);', $html);
        $this->assertStringContainsString('window.registerGallery(imgFiles.map(f => f.downloadUrl))', $html);
        $this->assertStringContainsString('return tpFileClick(event,${fileGalKey},${imgFiles.indexOf(f)})', $html);
        $this->assertStringContainsString('window.tpFileClick = function (ev, galleryKey, index)', $html);
        // "open in a new tab" shortcuts still work, and the gallery shows the ORIGINAL files
        $this->assertStringContainsString('ev.ctrlKey || ev.metaKey || ev.shiftKey', $html);
        $this->assertStringContainsString('imgLightbox(galleryKey, index);', $html);
    }

    public function test_images_attached_in_a_task_description_form_a_gallery_too(): void
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        $this->assertStringContainsString('const descGalKey = descImgUrls.length > 1', $html);
        $this->assertStringContainsString('`imgLightbox(${descGalKey},${_di})`', $html);
    }
}
