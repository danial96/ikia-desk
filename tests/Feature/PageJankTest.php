<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageJankTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_kanban_board_no_longer_rerenders_everything_whenever_anyone_touches_any_task(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($admin)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        $this->assertStringContainsString("Realtime.skip('kbVersion', 3)", $html);            // slow safety net while pushes work
        $this->assertStringContainsString('Date.now() - _kbLastFull < 60000', $html);          // at most one full re-render a minute
    }

    public function test_the_scroll_arrows_do_not_force_a_layout_every_600ms(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($admin)->get('/chat')->assertOk()->getContent();

        $this->assertStringNotContainsString('setInterval(update, 600)', $html);
        $this->assertStringContainsString('if (!document.hidden) update(); }, 2500)', $html);
    }
}
