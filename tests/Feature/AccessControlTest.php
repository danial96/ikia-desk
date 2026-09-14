<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'employee', array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'is_active' => true], $attrs));
    }

    private function makeTask(User $creator, array $attrs = []): Task
    {
        return Task::create(array_merge(['title' => 'Task ' . uniqid(), 'created_by' => $creator->id, 'priority' => 'medium'], $attrs));
    }

    // ── 1. Login throttling ──

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        // Even the correct password is refused while throttled
        $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many login attempts.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_successful_login_after_a_few_failures_still_works(): void
    {
        $user = $this->makeUser();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong']);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    // ── 2. Missing authorization ──

    public function test_only_task_members_can_comment(): void
    {
        $owner    = $this->makeUser();
        $outsider = $this->makeUser();
        $task     = $this->makeTask($owner);

        $this->actingAs($outsider)->post(route('tasks.comments.store', $task), ['content' => 'sneaky'])
            ->assertForbidden();
        $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['content' => 'hello'])
            ->assertRedirect();

        $this->assertDatabaseMissing('task_comments', ['content' => 'sneaky']);
        $this->assertDatabaseHas('task_comments', ['content' => 'hello']);
    }

    public function test_only_conversation_members_can_react_to_messages(): void
    {
        $member   = $this->makeUser();
        $outsider = $this->makeUser();
        $conv     = Conversation::create(['type' => 'group', 'name' => 'Private', 'created_by' => $member->id]);
        $conv->members()->attach($member->id);
        $msg = Message::create(['conversation_id' => $conv->id, 'user_id' => $member->id, 'content' => 'hi']);

        $this->actingAs($outsider)->postJson("/api/chat/msgs/{$msg->id}/react", ['emoji' => '👍'])->assertForbidden();
        $this->actingAs($member)->postJson("/api/chat/msgs/{$msg->id}/react", ['emoji' => '👍'])->assertOk();
    }

    public function test_bitrix_proxy_endpoints_are_admin_only(): void
    {
        $employee = $this->makeUser();

        $this->actingAs($employee)->getJson('/api/bitrix-tasks')->assertForbidden();
        $this->actingAs($employee)->getJson('/api/bitrix-task/123')->assertForbidden();
    }

    public function test_disk_file_requires_access_to_a_task_that_references_it(): void
    {
        $owner    = $this->makeUser();
        $outsider = $this->makeUser();
        $this->makeTask($owner, ['description' => 'See [disk file id=n777]']);

        $this->actingAs($outsider)->get('/api/disk-file/777')->assertForbidden();
        // Authorized; no BITRIX_WEBHOOK configured in tests, so the proxy then 404s
        $this->actingAs($owner)->get('/api/disk-file/777')->assertNotFound();
        // Similar id must not match (n7770 is not n777)
        $this->actingAs($owner)->get('/api/disk-file/77')->assertForbidden();
    }

    // ── 3. Permissions ──

    public function test_creating_projects_requires_permission(): void
    {
        $employee = $this->makeUser();
        $this->actingAs($employee)->post(route('projects.store'), ['name' => 'Nope'])->assertForbidden();

        $allowed = $this->makeUser('employee', ['permissions' => ['create_projects' => true]]);
        $this->actingAs($allowed)->post(route('projects.store'), ['name' => 'Yes'])->assertRedirect();

        $this->assertDatabaseMissing('projects', ['name' => 'Nope']);
        $this->assertDatabaseHas('projects', ['name' => 'Yes']);
    }

    public function test_project_page_only_lists_tasks_the_user_can_see(): void
    {
        $viewer  = $this->makeUser();
        $other   = $this->makeUser();
        $project = Project::create(['name' => 'Shared project', 'created_by' => $other->id]);
        $this->makeTask($viewer, ['project_id' => $project->id, 'title' => 'Visible task title']);
        $this->makeTask($other, ['project_id' => $project->id, 'title' => 'Hidden task title']);

        $this->actingAs($viewer)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Visible task title')
            ->assertDontSee('Hidden task title');
    }

    public function test_view_all_tasks_permission_grants_read_access(): void
    {
        $owner = $this->makeUser();
        $task  = $this->makeTask($owner);

        $this->actingAs($this->makeUser())->getJson("/api/local-task/{$task->id}")->assertForbidden();

        $viewer = $this->makeUser('employee', ['permissions' => ['view_all_tasks' => true]]);
        $this->actingAs($viewer)->getJson("/api/local-task/{$task->id}")->assertOk();
    }

    public function test_observers_can_view_task_details(): void
    {
        $owner    = $this->makeUser();
        $observer = $this->makeUser();
        $task     = $this->makeTask($owner);
        $task->observers()->attach($observer->id);

        $this->actingAs($observer)->getJson("/api/local-task/{$task->id}")->assertOk();
    }

    public function test_permission_update_only_stores_enforced_permissions(): void
    {
        $admin    = $this->makeUser('admin');
        $employee = $this->makeUser();

        $this->actingAs($admin)->post(route('permissions.update', $employee), [
            'create_projects' => '1', 'manage_employees' => '1', 'export_data' => '1',
        ])->assertRedirect();

        $this->assertSame(
            ['create_tasks' => false, 'view_all_tasks' => false, 'create_projects' => true],
            $employee->fresh()->permissions
        );
    }
}
