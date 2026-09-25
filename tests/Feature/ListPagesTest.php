<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_list_renders_as_a_table_with_the_bitrix_columns(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $p = Project::create(['name' => 'Alpha Site', 'color' => '#6366f1', 'created_by' => $admin->id]);
        Task::create(['title' => 'Fix header', 'created_by' => $admin->id, 'assigned_to' => $admin->id, 'project_id' => $p->id,
            'priority' => 'high', 'status' => 'in_progress', 'deadline' => now()->addDay()]);

        $this->actingAs($admin)->get(route('tasks.index', ['status' => 'in_progress']))->assertOk()
            ->assertSee('Created by')->assertSee('Assignee')->assertSee('Deadline')->assertSee('Fix header')->assertSee('Alpha Site')->assertSee('bx-row', false);

        // infinite-scroll JSON returns table rows
        $r = $this->actingAs($admin)->getJson(route('tasks.index', ['status' => 'in_progress']), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();
        $this->assertStringContainsString('<tr class="bx-row"', $r['html']);
    }

    public function test_projects_page_renders_as_a_table(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $p = Project::create(['name' => 'Beta Portal', 'color' => '#22c55e', 'created_by' => $admin->id]);
        Task::create(['title' => 'One', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'completed', 'project_id' => $p->id]);

        $this->actingAs($admin)->get(route('projects.index'))->assertOk()
            ->assertSee('Beta Portal')->assertSee('Performance')->assertSee('100%');
    }
}
