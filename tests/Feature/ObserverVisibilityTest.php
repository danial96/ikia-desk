<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** An observer must see the task in every listing a member/assignee sees it in — the List page and the Kanban board. */
class ObserverVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_observer_sees_the_task_in_the_list_and_kanban_board(): void
    {
        $owner    = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $observer = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $stranger = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $task = Task::create(['title' => 'Watched task', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'in_progress']);
        $task->observers()->attach($observer->id);

        $this->actingAs($observer)->get(route('tasks.index', ['status' => 'in_progress']))->assertOk()->assertSee('Watched task');
        $this->actingAs($observer)->getJson(route('tasks.kanban'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertJsonFragment(['title' => 'Watched task']);

        $this->actingAs($stranger)->get(route('tasks.index', ['status' => 'in_progress']))->assertOk()->assertDontSee('Watched task');
        $this->actingAs($stranger)->getJson(route('tasks.kanban'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertJsonMissing(['title' => 'Watched task']);
    }
}
