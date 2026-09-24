<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanDragPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'employee'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function moveTask(Task $task, User $as, ?string $deadline = '2026-12-01')
    {
        return $this->actingAs($as)->patchJson(route('tasks.move', $task), ['deadline' => $deadline]);
    }

    public function test_creator_can_move_the_card(): void
    {
        $creator = $this->makeUser();
        $task    = Task::create(['title' => 'T', 'created_by' => $creator->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->moveTask($task, $creator)->assertOk()->assertJson(['success' => true]);
    }

    public function test_assignee_can_move_the_card(): void
    {
        $creator  = $this->makeUser();
        $assignee = $this->makeUser();
        $task     = Task::create(['title' => 'T', 'created_by' => $creator->id, 'assigned_to' => $assignee->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->moveTask($task, $assignee)->assertOk()->assertJson(['success' => true]);
    }

    public function test_super_admin_can_move_any_card(): void
    {
        $creator = $this->makeUser();
        $admin   = $this->makeUser('super_admin');
        $task    = Task::create(['title' => 'T', 'created_by' => $creator->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->moveTask($task, $admin)->assertOk()->assertJson(['success' => true]);
    }

    public function test_plain_participant_member_cannot_move_the_card(): void
    {
        $creator     = $this->makeUser();
        $participant = $this->makeUser();
        $task        = Task::create(['title' => 'T', 'created_by' => $creator->id, 'priority' => 'medium', 'status' => 'in_progress']);
        $task->members()->attach($participant->id);

        $this->moveTask($task, $participant)->assertForbidden();

        // Deadline must be unchanged
        $this->assertNull($task->fresh()->deadline);
    }

    public function test_unrelated_employee_cannot_move_the_card(): void
    {
        $creator  = $this->makeUser();
        $outsider = $this->makeUser();
        $task     = Task::create(['title' => 'T', 'created_by' => $creator->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->moveTask($task, $outsider)->assertForbidden();
    }

    public function test_moving_a_card_preserves_the_existing_time_of_day(): void
    {
        $creator = $this->makeUser();
        $task    = Task::create([
            'title' => 'T', 'created_by' => $creator->id, 'priority' => 'medium', 'status' => 'in_progress',
            'deadline' => '2026-09-20 16:05:00',
        ]);

        // Drag sends a date-only string (moving between columns changes the date, not the time).
        $this->moveTask($task, $creator, '2026-10-05')->assertOk()->assertJson(['success' => true]);

        $fresh = $task->fresh();
        $this->assertSame('2026-10-05', $fresh->deadline->format('Y-m-d'));
        $this->assertSame('16:05:00', $fresh->deadline->format('H:i:s'));
    }

    public function test_moving_a_card_with_no_prior_deadline_defaults_to_midnight(): void
    {
        $creator = $this->makeUser();
        $task    = Task::create(['title' => 'T', 'created_by' => $creator->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->moveTask($task, $creator, '2026-10-05')->assertOk()->assertJson(['success' => true]);

        $fresh = $task->fresh();
        $this->assertSame('2026-10-05', $fresh->deadline->format('Y-m-d'));
        $this->assertSame('00:00:00', $fresh->deadline->format('H:i:s'));
    }

    public function test_observer_cannot_move_the_card(): void
    {
        $creator  = $this->makeUser();
        $observer = $this->makeUser();
        $task     = Task::create(['title' => 'T', 'created_by' => $creator->id, 'priority' => 'medium', 'status' => 'in_progress']);
        $task->observers()->attach($observer->id);

        $this->moveTask($task, $observer)->assertForbidden();

        // Deadline must be unchanged
        $this->assertNull($task->fresh()->deadline);
    }
}
