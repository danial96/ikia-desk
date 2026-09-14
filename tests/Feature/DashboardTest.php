<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_dashboard_counts_active_and_overdue(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        Task::create(['title' => 'Overdue', 'created_by' => $admin->id, 'priority' => 'high',
            'status' => 'in_progress', 'deadline' => now()->subDay()]);
        Task::create(['title' => 'Upcoming', 'created_by' => $admin->id, 'priority' => 'medium',
            'status' => 'in_progress', 'deadline' => now()->addWeek()]);
        Task::create(['title' => 'Done', 'created_by' => $admin->id, 'priority' => 'low',
            'status' => 'completed', 'deadline' => now()->subDay()]);
        Task::create(['title' => 'Paused', 'created_by' => $admin->id, 'priority' => 'low',
            'status' => 'paused']);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn($s) => $s['total_tasks'] === 4 && $s['my_tasks'] === 3 && $s['overdue_tasks'] === 1);
    }

    public function test_employee_dashboard_counts_only_their_overdue(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $other    = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        Task::create(['title' => 'Mine overdue', 'created_by' => $other->id, 'assigned_to' => $employee->id,
            'priority' => 'high', 'status' => 'in_progress', 'deadline' => now()->subDay()]);
        Task::create(['title' => 'Someone else overdue', 'created_by' => $other->id, 'assigned_to' => $other->id,
            'priority' => 'high', 'status' => 'in_progress', 'deadline' => now()->subDay()]);

        $this->actingAs($employee)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn($s) => $s['my_tasks'] === 1 && $s['overdue_tasks'] === 1);
    }

    public function test_a_task_can_be_saved_with_paused_status(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $task  = Task::create(['title' => 'Pause me', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($admin)->patchJson(route('tasks.field', $task), ['field' => 'status', 'value' => 'paused'])
            ->assertOk();

        $this->assertSame('paused', $task->fresh()->status);
    }
}
