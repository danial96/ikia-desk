<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(storage_path('logs/perf.log'));
        parent::tearDown();
    }

    public function test_a_report_is_written_to_its_own_log_with_only_the_expected_numbers(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        @unlink(storage_path('logs/perf.log'));

        $this->actingAs($u)->post('/api/perf', [
            'page' => '/tasks/kanban', 'ttfb' => 480, 'load' => 1900, 'long' => 7, 'longMs' => 2400, 'longest' => 900, 'cls' => '0.31',
            'cores' => 4, 'mem' => 4, 'net' => '4g', 'rtt' => 250, 'rt' => 1, 'stay' => 60,
            'evil' => 'drop table', 'ua' => 'x',
        ])->assertNoContent();

        $log = file_get_contents(storage_path('logs/perf.log'));
        $this->assertStringContainsString('"user":' . $u->id, $log);
        $this->assertStringContainsString('"longest":900', $log);
        $this->assertStringContainsString('"net":"4g"', $log);
        $this->assertStringNotContainsString('drop table', $log);
    }

    public function test_garbage_values_are_dropped_and_logged_out_browsers_are_refused(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        @unlink(storage_path('logs/perf.log'));

        $this->actingAs($u)->post('/api/perf', ['page' => '/chat<script>', 'ttfb' => 'abc', 'net' => 'warp', 'cores' => 99999])->assertNoContent();
        $log = file_get_contents(storage_path('logs/perf.log'));
        $this->assertStringNotContainsString('<script>', $log);
        $this->assertStringNotContainsString('abc', $log);
        $this->assertStringNotContainsString('warp', $log);
        $this->assertStringContainsString('"cores":256', $log);                 // clamped

        auth()->logout();
        $this->post('/api/perf', ['page' => '/x'])->assertRedirect();
    }

    public function test_pages_include_the_reporter(): void
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/chat')->assertOk()->getContent();
        $this->assertStringContainsString("navigator.sendBeacon('/api/perf'", $html);
        $this->assertStringContainsString("type: 'longtask'", $html);
    }
}
