<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCommentMentionReplyReactTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $owner): Task
    {
        return Task::create(['title' => 'Mentioned task', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'new']);
    }

    public function test_mentioning_someone_notifies_them_and_lets_them_open_the_task_without_adding_them(): void
    {
        $owner   = User::factory()->create(['is_active' => true]);
        $outside = User::factory()->create(['is_active' => true, 'role' => 'employee']);
        $task    = $this->task($owner);

        // not on the task yet — blocked
        $this->actingAs($outside)->getJson("/api/local-task/{$task->id}")->assertStatus(403);

        $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", [
            'content' => 'hey @' . $outside->name . ' check this',
            'mentions' => [$outside->id],
        ])->assertOk();

        $this->assertDatabaseHas('notifications', ['user_id' => $outside->id, 'type' => 'mention', 'task_id' => $task->id]);

        // now they can open it directly...
        $this->actingAs($outside)->getJson("/api/local-task/{$task->id}")->assertOk();
        // ...but they were never actually added to the task
        $this->assertFalse($task->fresh()->isMember($outside));

        // and still don't see it in the list/kanban
        $this->actingAs($outside)->get(route('tasks.index', ['status' => 'new']))->assertOk()->assertDontSee('Mentioned task');
    }

    public function test_replying_to_a_comment_notifies_its_author_and_the_reply_carries_the_parent(): void
    {
        $owner    = User::factory()->create(['is_active' => true]);
        $observer = User::factory()->create(['is_active' => true]);
        $task     = $this->task($owner);
        $task->observers()->attach($observer->id);

        $first = $this->actingAs($observer)->postJson("/api/local-task/{$task->id}/comment", ['content' => 'first note'])
            ->assertOk()->json('comment.id');

        Notification::query()->delete();   // clear the broad "commented" notifications, isolate the reply one

        $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", [
            'content' => 'replying to you', 'parent_id' => $first,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', ['user_id' => $observer->id, 'type' => 'mention', 'task_id' => $task->id]);

        $feed = $this->actingAs($owner)->getJson("/api/local-task/{$task->id}")->json('feed');
        $reply = collect($feed)->firstWhere('text', 'replying to you');
        $this->assertSame($first, $reply['parentId']);
        $this->assertSame('first note', $reply['parentPreview']['text']);
    }

    public function test_reacting_toggles_and_notifies_only_on_adding(): void
    {
        $owner  = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true]);
        $task   = $this->task($owner);
        $task->members()->attach($member->id);

        $cid = $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", ['content' => 'react to me'])
            ->assertOk()->json('comment.id');
        Notification::query()->delete();

        $this->actingAs($member)->postJson("/api/local-task/comments/{$cid}/react", ['emoji' => '👍'])
            ->assertOk()->assertJsonPath('reactions.👍.0', $member->id);
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'type' => 'mention']);

        Notification::query()->delete();
        // toggling off must not notify again
        $this->actingAs($member)->postJson("/api/local-task/comments/{$cid}/react", ['emoji' => '👍'])
            ->assertOk()->assertJson(['reactions' => []]);
        $this->assertDatabaseCount('notifications', 0);

        // someone with no access to the task can't react
        $stranger = User::factory()->create(['is_active' => true]);
        $this->actingAs($stranger)->postJson("/api/local-task/comments/{$cid}/react", ['emoji' => '👍'])->assertStatus(403);
    }
}
