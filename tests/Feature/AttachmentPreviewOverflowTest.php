<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentPreviewOverflowTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        return $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();
    }

    public function test_many_attachments_in_the_composer_collapse_into_a_plus_n_tile(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('const ATT_VISIBLE = 10;', $html);
        $this->assertStringContainsString("tile.textContent = expanded ? 'Less' : '+' + hidden;", $html);
        // the picker path and the drop/paste path both mark their chips so the overflow tile counts them
        $this->assertSame(2, substr_count($html, "chip.dataset.att = '1';"));
        $this->assertStringContainsString("pEl.dataset.expanded = ''; pEl.style.display = 'none';", $html);
    }

    public function test_the_bell_badge_centres_its_count_inside_the_pill(): void
    {
        $html = $this->page();

        // border-box plus a taller line-height than the box pushed "9+" out of the red pill: it is now a centred flex box
        $this->assertStringContainsString('id="notif-badge" style="display:none;position:absolute;top:0;right:0;box-sizing:border-box;min-width:18px;height:18px;align-items:center;justify-content:center', $html);
        $this->assertStringContainsString("badge.style.display = 'flex';", $html);
    }
}
