<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDeletePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $owner, ?User $assignee = null): Task
    {
        return Task::create(['title' => 'T', 'created_by' => $owner->id, 'assigned_to' => $assignee?->id, 'priority' => 'medium', 'status' => 'new']);
    }

    public function test_the_owner_and_admins_can_delete_but_others_cannot(): void
    {
        $owner    = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $assignee = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $other    = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $super    = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $t = $this->task($owner, $assignee);
        $this->actingAs($assignee)->delete(route('tasks.destroy', $t))->assertStatus(403);
        $this->actingAs($other)->delete(route('tasks.destroy', $t))->assertStatus(403);
        $this->assertNotNull(Task::find($t->id));

        $this->actingAs($owner)->delete(route('tasks.destroy', $t))->assertRedirect();
        $this->assertNull(Task::find($t->id));

        $t2 = $this->task($owner);
        $this->actingAs($admin)->delete(route('tasks.destroy', $t2))->assertRedirect();
        $this->assertNull(Task::find($t2->id));

        $t3 = $this->task($owner);
        $this->actingAs($super)->delete(route('tasks.destroy', $t3))->assertRedirect();
        $this->assertNull(Task::find($t3->id));
    }
}
