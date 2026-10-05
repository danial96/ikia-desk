<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiteModeTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $url = '/tasks/kanban?status=in_progress'): string
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        return $this->actingAs($u)->get($url)->assertOk()->getContent();
    }

    public function test_the_background_orbs_no_longer_animate_forever(): void
    {
        $html = $this->page();
        $this->assertStringContainsString('.bg-orb3, .bg-orb4 { animation: none !important; }', $html);
    }

    public function test_light_mode_is_applied_before_first_paint_and_removes_the_expensive_effects(): void
    {
        $html = $this->page();

        // set from the saved choice in <head>, so there is no flash of the heavy look
        $this->assertLessThan(strpos($html, '<body'), strpos($html, "localStorage.getItem('lite_ui')"));
        $this->assertStringContainsString("m === 'on' || m === 'auto-on'", $html);

        $this->assertStringContainsString('html.lite *, html.lite *::before, html.lite *::after { backdrop-filter: none !important;', $html);
        $this->assertStringContainsString('html.lite #sidebar, html.lite #topbar { background: rgba(10, 15, 60, .95) !important;', $html);   // solid instead of glass
        $this->assertStringContainsString('html.lite #right-panel', $html);
    }

    public function test_it_switches_on_by_itself_only_for_clearly_struggling_computers_judged_after_the_first_paint(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('steadyJank / steadyFrames <= 0.2', $html);           // more than 1 slow frame in 5
        $this->assertStringContainsString('steadyFrames < 30', $html);                          // needs enough samples
        $this->assertStringContainsString('performance.now() - t0 > 3000', $html);              // the heavy first paint is not counted
        $this->assertStringContainsString("liteMode() !== 'auto'", $html);                       // never overrides an explicit choice
        $this->assertStringContainsString("localStorage.setItem('lite_ui', 'auto-on')", $html);
        $this->assertStringContainsString('Desk switched to Light mode', $html);                 // and says so
    }

    public function test_the_choice_can_be_changed_in_the_profile(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $html = $this->actingAs($u)->get('/profile')->assertOk()->getContent();

        $this->assertStringContainsString('id="pf-lite"', $html);
        foreach (['value="auto"', 'value="on"', 'value="off"'] as $opt) $this->assertStringContainsString($opt, $html);
        $this->assertStringContainsString("localStorage.setItem('lite_ui', sel.value)", $html);
    }

    public function test_the_report_says_whether_light_mode_was_on(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        @unlink(storage_path('logs/perf.log'));
        $this->actingAs($u)->post('/api/perf', ['page' => '/chat', 'lite' => '1'])->assertNoContent();
        $this->assertStringContainsString('"lite":true', file_get_contents(storage_path('logs/perf.log')));
        @unlink(storage_path('logs/perf.log'));
    }
}
