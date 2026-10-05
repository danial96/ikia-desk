<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Build;
use App\Support\WhatsNew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateBannerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
    }

    public function test_every_response_carries_the_build_id(): void
    {
        $id = $this->actingAs($this->user())->get('/api/online-status')->assertOk()->headers->get('X-App-Build');

        $this->assertSame(Build::id(), $id);
        $this->assertSame(12, strlen($id));
        $this->assertSame($id, Build::id());                                  // stable between calls
        $this->get('/login')->assertHeader('X-App-Build', $id);              // also on pages for signed-out visitors
    }

    public function test_pages_know_their_own_build_and_the_notes_they_have_seen_and_hook_fetch(): void
    {
        $html = $this->actingAs($this->user())->get('/chat')->assertOk()->getContent();

        $this->assertStringContainsString('const mine = "' . Build::id() . '"', $html);
        $this->assertStringContainsString('const seenNotes = ' . WhatsNew::latestId(), $html);
        $this->assertStringContainsString("res.headers.get('X-App-Build')", $html);
        $this->assertStringContainsString('A new version of Desk is available.', $html);
        $this->assertStringContainsString("'/api/whats-new?since='", $html);
    }

    public function test_a_browser_on_an_old_version_gets_every_newer_release_oldest_first_capped_at_three(): void
    {
        $latest = WhatsNew::latestId();
        $this->assertGreaterThanOrEqual(3, $latest);

        $none = $this->actingAs($this->user())->getJson('/api/whats-new?since=' . $latest)->assertOk()->json('releases');
        $this->assertSame([], $none);

        $all = $this->actingAs($this->user())->getJson('/api/whats-new?since=0')->assertOk()->json('releases');
        $this->assertCount(3, $all);
        $ids = array_column($all, 'id');
        $sorted = $ids; sort($sorted);
        $this->assertSame($sorted, $ids);
        $this->assertSame($latest, end($ids));
        foreach ($all as $r) {
            $this->assertNotEmpty($r['items']);
            foreach ($r['items'] as $line) $this->assertIsString($line);
        }
    }

    public function test_the_notes_are_login_protected(): void
    {
        $this->getJson('/api/whats-new')->assertUnauthorized();
    }

    public function test_release_notes_file_is_well_formed(): void
    {
        $r = require resource_path('whatsnew.php');
        $ids = array_column($r, 'id');
        $this->assertSame(count($ids), count(array_unique($ids)));
        foreach ($r as $rel) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $rel['date']);
            $this->assertNotEmpty($rel['items']);
        }
    }
}
