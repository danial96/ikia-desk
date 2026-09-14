<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_mention_notifies_the_mentioned_member(): void
    {
        $me    = User::factory()->create(['is_active' => true]);
        $alice = User::factory()->create(['is_active' => true]);
        $conv  = Conversation::create(['type' => 'group', 'name' => 'Team', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $alice->id]);

        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", [
            'content'  => 'hey @Alice look at this',
            'mentions' => [$alice->id],
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $alice->id, 'actor_id' => $me->id, 'type' => 'mention', 'task_id' => null,
        ]);
    }

    public function test_chat_mention_ignores_non_members(): void
    {
        $me       = User::factory()->create(['is_active' => true]);
        $member   = User::factory()->create(['is_active' => true]);
        $outsider = User::factory()->create(['is_active' => true]);
        $conv     = Conversation::create(['type' => 'group', 'name' => 'Team', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $member->id]);

        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", [
            'content'  => 'hi',
            'mentions' => [$outsider->id],
        ])->assertOk();

        $this->assertDatabaseMissing('notifications', ['user_id' => $outsider->id, 'type' => 'mention']);
    }

    public function test_comment_mention_notifies_even_a_non_member(): void
    {
        $owner   = User::factory()->create(['is_active' => true]);
        $mention = User::factory()->create(['is_active' => true]);
        $task    = Task::create(['title' => 'Task', 'created_by' => $owner->id, 'priority' => 'medium']);

        $this->actingAs($owner)->postJson("/api/local-task/{$task->id}/comment", [
            'content'  => 'ping @someone',
            'mentions' => [$mention->id],
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $mention->id, 'actor_id' => $owner->id, 'type' => 'mention', 'task_id' => $task->id,
        ]);
    }
}
