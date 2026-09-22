<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function makeUser(string $role = 'employee'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_list_search_matches_title(): void
    {
        $admin = $this->admin();
        Task::create(['title' => 'Redesign the invoice page', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);
        Task::create(['title' => 'Unrelated task', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->actingAs($admin)->get(route('tasks.index', ['search' => 'invoice']))
            ->assertOk()->assertSee('Redesign the invoice page')->assertDontSee('Unrelated task');
    }

    public function test_list_search_matches_description(): void
    {
        $admin = $this->admin();
        Task::create(['title' => 'Client follow-up', 'description' => 'Discussed the WordPress migration timeline.', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);
        Task::create(['title' => 'Other task', 'description' => 'Nothing relevant here.', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->actingAs($admin)->get(route('tasks.index', ['search' => 'WordPress']))
            ->assertOk()->assertSee('Client follow-up')->assertDontSee('Other task');
    }

    public function test_list_search_matches_comment_content(): void
    {
        $admin = $this->admin();
        $task  = Task::create(['title' => 'Server maintenance', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);
        $other = Task::create(['title' => 'No match here', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);
        $task->comments()->create(['user_id' => $admin->id, 'content' => 'The database backup finished at 2am.', 'mentions' => []]);

        $this->actingAs($admin)->get(route('tasks.index', ['search' => 'database backup']))
            ->assertOk()->assertSee('Server maintenance')->assertDontSee('No match here');
    }

    public function test_kanban_search_matches_description_and_comments(): void
    {
        $admin = $this->admin();
        $byDesc = Task::create(['title' => 'Alpha', 'description' => 'Needs the login flow fixed.', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);
        $byComment = Task::create(['title' => 'Beta', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);
        $byComment->comments()->create(['user_id' => $admin->id, 'content' => 'login flow works now', 'mentions' => []]);
        Task::create(['title' => 'Gamma', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $res = $this->actingAs($admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('tasks.kanban', ['search' => 'login flow', 'status' => 'in_progress']));
        $res->assertOk();
        $titles = collect($res->json('columns'))->flatten(1)->pluck('title')->all();
        $this->assertContains('Alpha', $titles);
        $this->assertContains('Beta', $titles);
        $this->assertNotContains('Gamma', $titles);
    }

    public function test_search_does_not_bypass_task_visibility(): void
    {
        $owner    = $this->makeUser();
        $outsider = $this->makeUser();
        Task::create(['title' => 'Secret project notes', 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->actingAs($outsider)->get(route('tasks.index', ['search' => 'Secret']))
            ->assertOk()->assertDontSee('Secret project notes');
    }
}
