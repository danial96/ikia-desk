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

    public function test_a_full_page_load_with_no_filters_defaults_to_in_progress(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tasks.kanban'))
            ->assertRedirect(route('tasks.kanban', ['status' => 'in_progress']));
    }

    public function test_the_default_filter_redirect_keeps_deep_link_params_like_task_id(): void
    {
        $admin = $this->admin();

        // a notification / push link: it must still open that task after the redirect
        $this->actingAs($admin)->get('/tasks/kanban?task=42')
            ->assertRedirect(route('tasks.kanban', ['status' => 'in_progress', 'task' => 42]));
        $this->actingAs($admin)->get('/tasks/kanban?newtask=1&title=Hello')
            ->assertRedirect(route('tasks.kanban', ['status' => 'in_progress', 'newtask' => 1, 'title' => 'Hello']));
        $this->actingAs($admin)->get('/tasks?task=42')
            ->assertRedirect(route('tasks.kanban', ['status' => 'in_progress', 'task' => 42]));      // list page -> remembered Kanban layout in ONE hop, link kept
    }

    public function test_in_progress_filter_includes_new_pending_and_reviewing_but_not_paused(): void
    {
        $admin = $this->admin();
        Task::create(['title' => 'A new task title', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'new']);
        Task::create(['title' => 'A pending task title', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'pending']);
        Task::create(['title' => 'A paused task title', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'paused']);
        Task::create(['title' => 'An inprogress task title', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'in_progress']);

        $this->actingAs($admin)->get(route('tasks.kanban', ['status' => 'in_progress']))
            ->assertOk()
            ->assertSee('A new task title')
            ->assertSee('A pending task title')
            ->assertSee('An inprogress task title')
            ->assertDontSee('A paused task title');
    }

    public function test_clearing_the_filter_via_ajax_shows_all_active_tasks_without_bouncing_back(): void
    {
        $admin = $this->admin();
        Task::create(['title' => 'A paused task title', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'paused']);
        Task::create(['title' => 'An inprogress task title', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'in_progress']);

        // The live re-filter (X-Requested-With) with no status must not redirect back to the default.
        $res = $this->actingAs($admin)->getJson(route('tasks.kanban'), ['X-Requested-With' => 'XMLHttpRequest']);
        $res->assertOk();
        $this->assertStringContainsString('A paused task title', $res->getContent());
        $this->assertStringContainsString('An inprogress task title', $res->getContent());
    }

    public function test_recent_activity_moves_a_task_to_the_top_of_its_column(): void
    {
        $admin = $this->admin();
        $older = Task::create(['title' => 'Older No Deadline Task', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'new']);
        $newer = Task::create(['title' => 'Newer No Deadline Task', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'new']);
        \DB::table('tasks')->where('id', $older->id)->update(['updated_at' => now()->subDay()]);
        \DB::table('tasks')->where('id', $newer->id)->update(['updated_at' => now()->subHour()]);

        // Before any activity, order follows updated_at as it stands: newer task first.
        $this->actingAs($admin)->get(route('tasks.kanban', ['status' => 'in_progress']))
            ->assertOk()->assertSeeInOrder(['Newer No Deadline Task', 'Older No Deadline Task']);

        // Commenting on the older task is "recent activity" — it should now rank above the
        // untouched newer one, like Bitrix (previously only a direct field edit did this;
        // comments/attachments/checklist ticks never bumped the task at all).
        $older->fresh()->logActivity($admin, 'commented', null, null, 'hi');

        $this->actingAs($admin)->get(route('tasks.kanban', ['status' => 'in_progress']))
            ->assertOk()->assertSeeInOrder(['Older No Deadline Task', 'Newer No Deadline Task']);
    }

    public function test_log_activity_bumps_the_tasks_updated_at(): void
    {
        $admin = $this->admin();
        $task  = Task::create(['title' => 'Touch me', 'created_by' => $admin->id, 'priority' => 'low', 'status' => 'new']);
        \DB::table('tasks')->where('id', $task->id)->update(['updated_at' => now()->subDay()]);
        $before = $task->fresh()->updated_at;

        $task->fresh()->logActivity($admin, 'commented', null, null, 'hi');

        $this->assertTrue($task->fresh()->updated_at->gt($before));
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
