<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaptopScalingTest extends TestCase
{
    use RefreshDatabase;

    public function test_laptop_widths_get_the_desktop_layout_scaled_down(): void
    {
        $u    = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        foreach ([[1025, 1099, '.70'], [1100, 1199, '.75'], [1200, 1279, '.80'], [1280, 1365, '.85'], [1366, 1439, '.90'], [1440, 1535, '.93']] as [$min, $max, $z]) {
            $this->assertStringContainsString("@media (min-width: {$min}px) and (max-width: {$max}px) { :root { --uz: {$z}; } }", $html);
        }
        $this->assertStringContainsString('#sidebar, #main-content, #right-panel { zoom: var(--uz); }', $html);
        $this->assertStringContainsString('#tp-panel, #tp-side-actions, #nt-panel, #chat-panel { zoom: var(--uz); }', $html);
        // full-height layouts are measured in the scaled units
        $this->assertStringContainsString('calc(var(--vh100, 100vh) - 210px)', $html);
        // the kanban's custom scrollbar thumb follows the mouse 1:1 on a scaled page
        $this->assertStringContainsString('const dx      = (e.clientX - startX) / uz;', $html);
    }
}
