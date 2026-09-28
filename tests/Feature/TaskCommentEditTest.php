<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCommentEditTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $owner): Task
    {
        return Task::create(['title' => 'Editable task', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'new']);
    }

    public function test_the_author_can_edit_their_own_comment(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $task  = $this->task($owner);

        $cid = $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", ['content' => 'original text'])
            ->assertOk()->json('comment.id');

        $this->actingAs($owner)->patchJson("/api/local-task/comments/{$cid}", ['content' => 'edited text'])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('task_comments', ['id' => $cid, 'content' => 'edited text']);
        $this->assertNotNull(\App\Models\TaskComment::find($cid)->edited_at);
    }

    public function test_someone_else_cannot_edit_the_comment(): void
    {
        $owner  = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true]);
        $task   = $this->task($owner);
        $task->members()->attach($member->id);

        $cid = $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", ['content' => 'original text'])
            ->assertOk()->json('comment.id');

        $this->actingAs($member)->patchJson("/api/local-task/comments/{$cid}", ['content' => 'hijacked'])
            ->assertStatus(403);

        $this->assertDatabaseHas('task_comments', ['id' => $cid, 'content' => 'original text']);
    }

    public function test_editing_is_blocked_after_24_hours(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $task  = $this->task($owner);

        $comment = $task->comments()->create(['user_id' => $owner->id, 'content' => 'old comment']);
        \DB::table('task_comments')->where('id', $comment->id)->update(['created_at' => now()->subHours(25)]);

        $this->actingAs($owner)->patchJson("/api/local-task/comments/{$comment->id}", ['content' => 'too late'])
            ->assertStatus(403);
    }
}
