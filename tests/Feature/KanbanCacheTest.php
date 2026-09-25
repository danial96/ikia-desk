<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deleted_task_disappears_from_the_board_immediately(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $task  = Task::create(['title' => 'Doomed task', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($admin)->get(route('tasks.kanban'))->assertOk()->assertSee('Doomed task');   // warms the 30s cache

        $this->actingAs($admin)->delete(route('tasks.destroy', $task))->assertRedirect();

        $this->actingAs($admin)->get(route('tasks.kanban'))->assertOk()->assertDontSee('Doomed task');
    }
}
