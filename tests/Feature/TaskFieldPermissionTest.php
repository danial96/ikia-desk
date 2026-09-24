<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFieldPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'employee'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function setField(Task $task, User $as, string $field, $value)
    {
        return $this->actingAs($as)->patchJson(route('tasks.field', $task), ['field' => $field, 'value' => $value]);
    }

    public function test_assignee_can_set_deadline(): void
    {
        $creator  = $this->makeUser();
        $assignee = $this->makeUser();
        $task     = Task::create(['title' => 'T', 'created_by' => $creator->id, 'assigned_to' => $assignee->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->setField($task, $assignee, 'deadline', '2026-12-01T09:00:00')->assertOk()->assertJson(['success' => true]);
        $this->assertNotNull($task->fresh()->deadline);
    }

    public function test_assignee_can_set_priority(): void
    {
        $creator  = $this->makeUser();
        $assignee = $this->makeUser();
        $task     = Task::create(['title' => 'T', 'created_by' => $creator->id, 'assigned_to' => $assignee->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->setField($task, $assignee, 'priority', 'urgent')->assertOk()->assertJson(['success' => true]);
        $this->assertSame('urgent', $task->fresh()->priority);
    }

    public function test_plain_participant_cannot_set_deadline(): void
    {
        $creator     = $this->makeUser();
        $participant = $this->makeUser();
        $task        = Task::create(['title' => 'T', 'created_by' => $creator->id, 'priority' => 'medium', 'status' => 'in_progress']);
        $task->members()->attach($participant->id);

        $this->setField($task, $participant, 'deadline', '2026-12-01T09:00:00')->assertForbidden();
        $this->assertNull($task->fresh()->deadline);
    }

    public function test_assignee_cannot_reassign_the_task(): void
    {
        $creator  = $this->makeUser();
        $assignee = $this->makeUser();
        $other    = $this->makeUser();
        $task     = Task::create(['title' => 'T', 'created_by' => $creator->id, 'assigned_to' => $assignee->id, 'priority' => 'medium', 'status' => 'in_progress']);

        // Assignee may manage deadline/priority but not hand the task to someone else.
        $this->setField($task, $assignee, 'assigned_to', $other->id)->assertForbidden();
        $this->assertSame($assignee->id, $task->fresh()->assigned_to);
    }

    public function test_creator_can_reassign_the_task(): void
    {
        $creator = $this->makeUser();
        $other   = $this->makeUser();
        $task    = Task::create(['title' => 'T', 'created_by' => $creator->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->setField($task, $creator, 'assigned_to', $other->id)->assertOk()->assertJson(['success' => true]);
        $this->assertSame($other->id, $task->fresh()->assigned_to);
    }
}
