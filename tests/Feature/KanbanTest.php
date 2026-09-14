<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    public function test_load_completed_paginates(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 60; $i++) {
            Task::create(['title' => "Done $i", 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'completed']);
        }

        // First extra page (after the initial 50 shown on the board)
        $this->actingAs($admin)->getJson(route('tasks.kanban.completed', ['offset' => 50]))
            ->assertOk()
            ->assertJson(['hasMore' => false, 'offset' => 60, 'total' => 60]);

        // From the start: 50 returned, more remain
        $this->actingAs($admin)->getJson(route('tasks.kanban.completed', ['offset' => 0]))
            ->assertOk()
            ->assertJson(['hasMore' => true, 'offset' => 50, 'total' => 60]);
    }

    public function test_kanban_without_status_shows_all_active_not_just_in_progress(): void
    {
        $admin = $this->admin();
        Task::create(['title' => 'A new task title', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'new']);
        Task::create(['title' => 'A paused task title', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'paused']);
        Task::create(['title' => 'An inprogress task title', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'in_progress']);

        $this->actingAs($admin)->get(route('tasks.kanban'))
            ->assertOk()
            ->assertSee('A new task title')
            ->assertSee('A paused task title')
            ->assertSee('An inprogress task title');
    }

    public function test_load_completed_respects_task_visibility(): void
    {
        $owner    = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $outsider = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        for ($i = 0; $i < 3; $i++) {
            Task::create(['title' => "Owner done $i", 'created_by' => $owner->id, 'priority' => 'low', 'status' => 'completed']);
        }

        $this->actingAs($outsider)->getJson(route('tasks.kanban.completed', ['offset' => 0]))
            ->assertOk()
            ->assertJson(['total' => 0]);
    }
}
