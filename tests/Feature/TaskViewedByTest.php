<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskViewedByTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_task_records_a_view_and_lists_it_in_viewed_by(): void
    {
        $owner  = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true]);
        $task   = Task::create(['title' => 'T', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'new']);
        $task->members()->attach($member->id);

        $resp = $this->actingAs($member)->getJson("/api/local-task/{$task->id}")->assertOk();

        $viewedBy = $resp->json('viewedBy');
        $this->assertCount(1, $viewedBy);
        $this->assertSame($member->id, $viewedBy[0]['id']);
        $this->assertNotNull($viewedBy[0]['viewedAt']);

        $this->assertDatabaseHas('task_views', ['task_id' => $task->id, 'user_id' => $member->id]);
    }

    public function test_reopening_the_task_refreshes_the_same_view_row_instead_of_duplicating_it(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $task  = Task::create(['title' => 'T', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($owner)->getJson("/api/local-task/{$task->id}")->assertOk();
        $this->actingAs($owner)->getJson("/api/local-task/{$task->id}")->assertOk();

        $this->assertDatabaseCount('task_views', 1);
    }

    public function test_last_seen_by_shows_the_most_recent_other_viewer_after_the_latest_activity(): void
    {
        $owner    = User::factory()->create(['is_active' => true]);
        $reader   = User::factory()->create(['is_active' => true]);
        $task     = Task::create(['title' => 'T', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'new']);
        $task->members()->attach($reader->id);

        // Owner comments first...
        $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", ['content' => 'hello'])->assertOk();

        // ...then the reader opens the task afterwards. From the reader's OWN perspective there's
        // no one else who has seen it yet (their own fresh view doesn't count as "seen by" to
        // themselves), so this is null for them.
        $resp = $this->actingAs($reader)->getJson("/api/local-task/{$task->id}")->assertOk();
        $this->assertNull($resp->json('lastSeenBy'));

        // The owner reloading their own task, however, sees that someone ELSE (the reader) has
        // caught up on it since their comment.
        $ownResp = $this->actingAs($owner)->getJson("/api/local-task/{$task->id}")->assertOk();
        $this->assertSame($reader->name, $ownResp->json('lastSeenBy.name'));
    }

    public function test_a_user_is_never_shown_as_having_seen_their_own_reload(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $task  = Task::create(['title' => 'T', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", ['content' => 'hello'])->assertOk();
        $resp = $this->actingAs($owner)->getJson("/api/local-task/{$task->id}")->assertOk();

        $this->assertNull($resp->json('lastSeenBy'));
    }

    public function test_the_task_panels_background_poll_does_not_write_a_view_every_3_seconds(): void
    {
        // The task panel re-fetches this same endpoint every 3s for as long as it's open, for
        // every user who has it open — writing a view on every one of those ticks would mean a
        // constant stream of DB writes with no real benefit (viewing doesn't need that freshness).
        $owner = User::factory()->create(['is_active' => true]);
        $task  = Task::create(['title' => 'T', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($owner)->getJson("/api/local-task/{$task->id}?background=1")->assertOk();
        $this->assertDatabaseCount('task_views', 0);

        // A real (non-background) open still records it as before.
        $this->actingAs($owner)->getJson("/api/local-task/{$task->id}")->assertOk();
        $this->assertDatabaseCount('task_views', 1);
    }
}
