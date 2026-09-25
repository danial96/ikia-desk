<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTrashTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $owner, string $title = 'T'): Task
    {
        return Task::create(['title' => $title, 'created_by' => $owner->id, 'priority' => 'medium', 'status' => 'new']);
    }

    public function test_deleting_moves_the_task_to_the_trash_and_remembers_who_did_it(): void
    {
        $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $t = $this->task($owner, 'Old news');

        $this->actingAs($owner)->delete(route('tasks.destroy', $t))->assertRedirect();

        $this->assertNull(Task::find($t->id));
        $trashed = Task::onlyTrashed()->find($t->id);
        $this->assertNotNull($trashed);
        $this->assertSame($owner->id, (int) $trashed->deleted_by);
    }

    public function test_owner_sees_only_their_own_trash_admin_sees_everything(): void
    {
        $a = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $b = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->task($a, 'Alpha task')->delete();
        $this->task($b, 'Beta task')->delete();

        $this->actingAs($a)->get(route('tasks.trash'))->assertOk()->assertSee('Alpha task')->assertDontSee('Beta task');
        $this->actingAs($admin)->get(route('tasks.trash'))->assertOk()->assertSee('Alpha task')->assertSee('Beta task');
    }

    public function test_only_the_owner_or_an_admin_can_restore(): void
    {
        $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $t = $this->task($owner, 'Bring me back');
        $t->delete();

        $this->actingAs($other)->post(route('tasks.restore', $t->id))->assertStatus(403);
        $this->assertNull(Task::find($t->id));

        $this->actingAs($owner)->post(route('tasks.restore', $t->id))->assertRedirect(route('tasks.trash'));
        $this->assertNotNull(Task::find($t->id));

        $t->delete();
        $this->actingAs($admin)->post(route('tasks.restore', $t->id))->assertRedirect();
        $this->assertNotNull(Task::find($t->id));
        $this->assertNull(Task::find($t->id)->deleted_by);
    }

    public function test_the_trash_link_is_only_shown_to_admins_and_task_owners(): void
    {
        $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $nobody = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->task($owner);

        $this->actingAs($owner)->get(route('dashboard'))->assertSee(route('tasks.trash'), false);
        $this->actingAs($admin)->get(route('dashboard'))->assertSee(route('tasks.trash'), false);
        $this->actingAs($nobody)->get(route('dashboard'))->assertDontSee(route('tasks.trash'), false);
    }

    public function test_permanent_delete_removes_the_task_and_its_data_for_owner_and_admin_only(): void
    {
        $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $t = $this->task($owner, 'Gone for good');
        $t->comments()->create(['user_id' => $owner->id, 'content' => 'hello']);
        $t->delete();

        $this->actingAs($other)->delete(route('tasks.force', $t->id))->assertStatus(403);
        $this->assertNotNull(Task::onlyTrashed()->find($t->id));

        $this->actingAs($owner)->delete(route('tasks.force', $t->id))->assertRedirect(route('tasks.trash'));
        $this->assertNull(Task::withTrashed()->find($t->id));
        $this->assertSame(0, \DB::table('task_comments')->where('task_id', $t->id)->count());

        // a task that is NOT in the trash can't be force-deleted through this route
        $live = $this->task($owner, 'Still alive');
        $this->actingAs($admin)->delete(route('tasks.force', $live->id))->assertStatus(404);
        $this->assertNotNull(Task::find($live->id));

        $t2 = $this->task($owner, 'Admin removes');
        $t2->delete();
        $this->actingAs($admin)->delete(route('tasks.force', $t2->id))->assertRedirect();
        $this->assertNull(Task::withTrashed()->find($t2->id));
    }
}
