<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHandoverDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    public function test_cannot_delete_a_still_active_employee(): void
    {
        $admin = $this->superAdmin();
        $leaving = User::factory()->create(['is_active' => true]);
        $handover = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('employees.handover-delete', $leaving), [
            'handover_to' => $handover->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $leaving->id]);
        $this->assertNotSame('deleted', $leaving->fresh()->email); // still exists, untouched
    }

    public function test_handover_reassigns_every_task_role_and_leaves_a_note(): void
    {
        $admin    = $this->superAdmin();
        $leaving  = User::factory()->create(['is_active' => false]);
        $handover = User::factory()->create(['is_active' => true]);

        $owned    = Task::create(['title' => 'Owned', 'created_by' => $leaving->id, 'priority' => 'medium', 'status' => 'new']);
        $assigned = Task::create(['title' => 'Assigned', 'created_by' => $admin->id, 'assigned_to' => $leaving->id, 'priority' => 'medium', 'status' => 'new']);
        $memberOf = Task::create(['title' => 'Member', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'new']);
        $memberOf->members()->attach($leaving->id);
        $observed = Task::create(['title' => 'Observed', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'new']);
        $observed->observers()->attach($leaving->id);

        $this->actingAs($admin)->post(route('employees.handover-delete', $leaving), [
            'handover_to' => $handover->id,
        ])->assertRedirect();

        $this->assertSame($handover->id, $owned->fresh()->created_by);
        $this->assertSame($handover->id, $assigned->fresh()->assigned_to);
        $this->assertTrue($memberOf->fresh()->members->contains($handover->id));
        $this->assertFalse($memberOf->fresh()->members->contains($leaving->id));
        $this->assertTrue($observed->fresh()->observers->contains($handover->id));
        $this->assertFalse($observed->fresh()->observers->contains($leaving->id));

        foreach ([$owned, $assigned, $memberOf, $observed] as $task) {
            $this->assertDatabaseHas('task_comments', [
                'task_id' => $task->id, 'is_system' => true,
            ]);
        }
        $note = $owned->fresh()->comments()->where('is_system', true)->first();
        $this->assertStringContainsString($leaving->name, $note->content);
        $this->assertStringContainsString($handover->name, $note->content);
    }

    public function test_employee_with_no_other_history_is_actually_deleted(): void
    {
        $admin    = $this->superAdmin();
        $leaving  = User::factory()->create(['is_active' => false]);
        $handover = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('employees.handover-delete', $leaving), [
            'handover_to' => $handover->id,
        ])->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $leaving->id]);
    }

    public function test_employee_with_chat_history_stays_deactivated_instead_of_deleted(): void
    {
        $admin    = $this->superAdmin();
        $leaving  = User::factory()->create(['is_active' => false]);
        $handover = User::factory()->create(['is_active' => true]);
        \App\Models\Conversation::create(['type' => 'general'])
            ->messages()->create(['user_id' => $leaving->id, 'content' => 'hi']);

        $this->actingAs($admin)->post(route('employees.handover-delete', $leaving), [
            'handover_to' => $handover->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $leaving->id, 'is_active' => false]);
    }

    public function test_handover_target_must_be_an_active_employee(): void
    {
        $admin    = $this->superAdmin();
        $leaving  = User::factory()->create(['is_active' => false]);
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->post(route('employees.handover-delete', $leaving), [
            'handover_to' => $inactive->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $leaving->id]);
    }

    public function test_only_super_admin_can_delete_an_employee(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $leaving  = User::factory()->create(['is_active' => false]);
        $handover = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('employees.handover-delete', $leaving), [
            'handover_to' => $handover->id,
        ])->assertForbidden();
    }
}
