<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentPreviewOverflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_many_attachments_in_the_composer_collapse_into_a_plus_n_tile(): void
    {
        $u    = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        $this->assertStringContainsString('const ATT_VISIBLE = 10;', $html);
        $this->assertStringContainsString("tile.textContent = expanded ? 'Less' : '+' + hidden;", $html);
        // both upload paths (file picker and drop/paste) and removing/clearing keep the tile in step
        $this->assertSame(2, substr_count($html, "chip.dataset.att = '1';"));      // the picker path and the drop/paste path both mark their chips
        $this->assertStringContainsString("pEl.dataset.expanded = ''; pEl.style.display = 'none';", $html);
    }
}
