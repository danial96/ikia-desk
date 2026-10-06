<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanCardAccessTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $creator): Task
    {
        return Task::create(['title' => 'Card', 'created_by' => $creator->id, 'priority' => 'low', 'status' => 'new']);
    }

    public function test_everyone_who_can_open_a_task_can_refresh_its_card(): void
    {
        $owner  = User::factory()->create(['is_active' => true]);
        $viewer = User::factory()->create(['is_active' => true, 'permissions' => ['view_all_tasks' => true]]);   // sees every task on the board
        $admin  = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $task   = $this->task($owner);

        foreach ([$owner, $viewer, $admin] as $u) {
            $this->actingAs($u)->getJson("/api/kanban-task/{$task->id}")->assertOk()->assertJsonPath('id', $task->id);
        }
    }

    public function test_a_stranger_still_gets_a_403(): void
    {
        $owner    = User::factory()->create(['is_active' => true]);
        $stranger = User::factory()->create(['is_active' => true]);

        $this->actingAs($stranger)->getJson('/api/kanban-task/' . $this->task($owner)->id)->assertForbidden();
    }

    public function test_a_burst_of_changes_to_one_task_refreshes_its_card_once(): void
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        $this->assertStringContainsString('window._kbCardTimers[d.t] = setTimeout(() => kbUpdateCard(d.t), 500);', $html);
    }
}
