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

    public function test_the_author_or_an_admin_can_delete_a_comment_but_another_member_cannot(): void
    {
        $owner  = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true]);
        $admin  = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $task   = $this->task($owner);
        $task->members()->attach($member->id);

        $mk = fn () => $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", ['content' => '[file name="database.sql"]/uploads/up_x.sql[/file]'])
            ->assertOk()->json('comment.id');

        $c1 = $mk();
        $this->actingAs($member)->deleteJson("/api/local-task/comments/{$c1}")->assertStatus(403);
        $this->assertNotNull(\App\Models\TaskComment::find($c1));

        $this->actingAs($owner)->deleteJson("/api/local-task/comments/{$c1}")->assertOk();
        $this->assertNull(\App\Models\TaskComment::find($c1));

        $c2 = $mk();
        $this->actingAs($admin)->deleteJson("/api/local-task/comments/{$c2}")->assertOk();
        $this->assertNull(\App\Models\TaskComment::find($c2));
    }

    public function test_a_comment_with_attachments_can_still_be_edited_and_an_attachment_dropped(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $task  = $this->task($owner);
        $raw   = "[file name=\"a.sql\"]/uploads/up_a.sql[/file]
[file name=\"b.sql\"]/uploads/up_b.sql[/file]
caption";
        $cid   = $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", ['content' => $raw])->assertOk()->json('comment.id');

        $kept = "[file name=\"b.sql\"]/uploads/up_b.sql[/file]
new caption";
        $this->actingAs($owner)->patchJson("/api/local-task/comments/{$cid}", ['content' => $kept])->assertOk();
        $this->assertDatabaseHas('task_comments', ['id' => $cid, 'content' => $kept]);
    }

    public function test_the_task_comment_menu_offers_delete_and_edit_for_comments_with_attachments(): void
    {
        $u    = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        $this->assertStringContainsString('data-action="delete"', $html);
        $this->assertStringContainsString("appDeleteConfirm('/api/local-task/comments/' + id", $html);
        $this->assertStringContainsString('window.tpDropEditTag = function', $html);
        $this->assertStringNotContainsString('!/\[(img|file|voice)/i.test(raw)', $html);   // edit is no longer blocked by attachments
    }
}
