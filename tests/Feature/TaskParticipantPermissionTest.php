<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskParticipantPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'employee'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function makeTask(User $creator, ?User $assignee = null): Task
    {
        return Task::create([
            'title' => 'T', 'created_by' => $creator->id, 'assigned_to' => $assignee?->id,
            'priority' => 'medium', 'status' => 'in_progress',
        ]);
    }

    public function test_assignee_can_add_a_participant(): void
    {
        $creator  = $this->makeUser();
        $assignee = $this->makeUser();
        $other    = $this->makeUser();
        $task     = $this->makeTask($creator, $assignee);

        $this->actingAs($assignee)->postJson(route('tasks.participants.toggle', $task), ['user_id' => $other->id])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($task->fresh()->members()->where('user_id', $other->id)->exists());
    }

    public function test_assignee_can_add_an_observer(): void
    {
        $creator  = $this->makeUser();
        $assignee = $this->makeUser();
        $other    = $this->makeUser();
        $task     = $this->makeTask($creator, $assignee);

        $this->actingAs($assignee)->postJson(route('tasks.observers.toggle', $task), ['user_id' => $other->id])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($task->fresh()->observers()->where('user_id', $other->id)->exists());
    }

    public function test_plain_participant_cannot_add_a_participant(): void
    {
        $creator     = $this->makeUser();
        $participant = $this->makeUser();
        $other       = $this->makeUser();
        $task        = $this->makeTask($creator);
        $task->members()->attach($participant->id);

        $this->actingAs($participant)->postJson(route('tasks.participants.toggle', $task), ['user_id' => $other->id])
            ->assertForbidden();
        $this->assertFalse($task->fresh()->members()->where('user_id', $other->id)->exists());
    }

    public function test_an_observer_can_take_themselves_off_but_not_add_or_remove_anyone_else(): void
    {
        $creator  = $this->makeUser();
        $watcher  = $this->makeUser();
        $other    = $this->makeUser();
        $task     = $this->makeTask($creator);
        $task->observers()->attach([$watcher->id, $other->id]);

        // not someone else, and not adding anybody
        $this->actingAs($watcher)->postJson(route('tasks.observers.toggle', $task), ['user_id' => $other->id])->assertForbidden();
        $newbie = $this->makeUser();
        $this->actingAs($watcher)->postJson(route('tasks.observers.toggle', $task), ['user_id' => $newbie->id])->assertForbidden();
        $this->assertSame(2, $task->fresh()->observers()->count());

        // themselves: fine, and the page is told the task is no longer theirs to open
        $this->actingAs($watcher)->postJson(route('tasks.observers.toggle', $task), ['user_id' => $watcher->id])
            ->assertOk()->assertJson(['success' => true, 'action' => 'removed', 'left' => true]);
        $this->assertFalse($task->fresh()->observers()->where('user_id', $watcher->id)->exists());

        // and, no longer an observer, they cannot add themselves back
        $this->actingAs($watcher)->postJson(route('tasks.observers.toggle', $task), ['user_id' => $watcher->id])->assertForbidden();
    }
}
