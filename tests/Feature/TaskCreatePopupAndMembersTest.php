<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCreatePopupAndMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_task_creation_returns_the_new_tasks_id_and_title_for_the_view_task_popup(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $resp = $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'Brand new task', 'priority' => 'medium',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $resp->assertJsonPath('success', true);
        $task = Task::where('title', 'Brand new task')->firstOrFail();
        $resp->assertJsonPath('task.id', $task->id);
        $resp->assertJsonPath('task.title', 'Brand new task');
    }

    public function test_local_task_detail_includes_each_persons_job_position_for_the_members_panel(): void
    {
        $owner  = User::factory()->create(['is_active' => true, 'position' => 'Project Manager']);
        $member = User::factory()->create(['is_active' => true, 'position' => 'CMS Manager']);
        $task   = Task::create(['title' => 'T', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'new']);
        $task->members()->attach($member->id);

        $resp = $this->actingAs($owner)->getJson("/api/local-task/{$task->id}")->assertOk();

        $resp->assertJsonPath('creator.position', 'Project Manager');
        $resp->assertJsonPath('participants.0.position', 'CMS Manager');
    }
}
