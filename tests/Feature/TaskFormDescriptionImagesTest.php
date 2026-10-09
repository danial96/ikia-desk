<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFormDescriptionImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_attached_images_also_show_inside_the_description_box_which_is_twice_as_tall(): void
    {
        $u    = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        $this->assertStringContainsString('id="nt-desc-inline"', $html);                       // the strip inside the description card
        $this->assertStringContainsString('function ntRenderInlineImages()', $html);
        $this->assertStringContainsString('ntRenderInlineImages();', $html);
        $this->assertStringContainsString('#nt-desc-card textarea { font-size:15px !important; padding:16px 20px !important; min-height:220px !important; }', $html);
        // the Files list under the box is still drawn as before
        $this->assertStringContainsString("'<span style=\"font-size:13px;font-weight:600;color:#374151;\">Files: '", $html);
    }
}
