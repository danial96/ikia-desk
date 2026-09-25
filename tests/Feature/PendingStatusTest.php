<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bitrix's "Pending" (status 2) is its own status now instead of being folded into In Progress. */
class PendingStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    public function test_a_task_can_be_set_to_pending(): void
    {
        $admin = $this->admin();
        $task  = Task::create(['title' => 'T', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($admin)->patchJson(route('tasks.field', $task), ['field' => 'status', 'value' => 'pending'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('pending', $task->fresh()->status);

        $this->actingAs($admin)->patchJson(route('tasks.move', $task), ['status' => 'pending'])->assertOk();
    }

    public function test_the_in_progress_filter_also_lists_pending_tasks(): void
    {
        $admin = $this->admin();
        Task::create(['title' => 'Working on it', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);
        Task::create(['title' => 'Accepted not started', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'pending']);
        Task::create(['title' => 'Paused one', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'paused']);

        $this->actingAs($admin)->get(route('tasks.index', ['status' => 'in_progress']))
            ->assertOk()->assertSee('Working on it')->assertSee('Accepted not started')->assertDontSee('Paused one');

        // an exact "pending" filter shows only pending
        $this->actingAs($admin)->get(route('tasks.index', ['status' => 'pending']))
            ->assertOk()->assertSee('Accepted not started')->assertDontSee('Working on it');
    }

    public function test_task_json_exposes_the_bitrix_id_and_real_created_time(): void
    {
        $admin = $this->admin();
        $task  = Task::create(['title' => 'T', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'pending', 'bitrix_id' => 23335]);

        $this->actingAs($admin)->getJson(route('api.local.task', $task->id))
            ->assertOk()->assertJsonPath('task.bitrixId', 23335)->assertJsonPath('task.status', 'pending');
    }
}
