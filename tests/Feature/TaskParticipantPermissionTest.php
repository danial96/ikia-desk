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
}
