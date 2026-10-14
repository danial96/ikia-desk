<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPanelBadCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_error_reply_is_never_cached_or_drawn_as_a_task(): void
    {
        $u    = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        $this->assertStringContainsString("if (cached && !cached.task) { cached = null;", $html);          // an old bad entry is dropped on read
        $this->assertStringContainsString("if (!data || !data.task) { if (!cached)", $html);                 // an error reply is not stored
        $this->assertStringContainsString("function tpRenderLocal(data) {\n    if (!data || !data.task) return;", str_replace("\r\n", "\n", $html));
    }
}
