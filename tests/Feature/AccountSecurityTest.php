<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'is_active' => true], $attrs));
    }

    public function test_admin_cannot_promote_self_to_super_admin(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->put(route('employees.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'super_admin',
        ])->assertForbidden();

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_cannot_edit_super_admin_account(): void
    {
        $admin = $this->makeUser('admin');
        $super = $this->makeUser('super_admin');

        $this->actingAs($admin)->put(route('employees.update', $super), [
            'name' => $super->name, 'email' => 'attacker@example.com', 'role' => 'employee', 'password' => 'newpassword123',
        ])->assertForbidden();

        $this->assertSame('super_admin', $super->fresh()->role);
        $this->assertTrue(Hash::check('password', $super->fresh()->password));
    }

    public function test_admin_cannot_deactivate_super_admin(): void
    {
        $admin = $this->makeUser('admin');
        $super = $this->makeUser('super_admin');

        $this->actingAs($admin)->post(route('employees.toggle-active', $super))->assertForbidden();

        $this->assertTrue($super->fresh()->is_active);
    }

    public function test_admin_cannot_change_super_admin_password_via_profile(): void
    {
        $admin = $this->makeUser('admin');
        $super = $this->makeUser('super_admin');

        $this->actingAs($admin)->post(route('profile.update.user', $super->id), [
            'name' => $super->name, 'email' => $super->email,
            'password' => 'newpassword123', 'password_confirmation' => 'newpassword123',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('password', $super->fresh()->password));
    }

    public function test_admin_can_still_edit_employee(): void
    {
        $admin    = $this->makeUser('admin');
        $employee = $this->makeUser('employee');

        $this->actingAs($admin)->put(route('employees.update', $employee), [
            'name' => 'Renamed', 'email' => $employee->email, 'role' => 'employee',
        ])->assertRedirect();

        $this->assertSame('Renamed', $employee->fresh()->name);
    }

    public function test_super_admin_can_promote_admin(): void
    {
        $super = $this->makeUser('super_admin');
        $admin = $this->makeUser('admin');

        $this->actingAs($super)->put(route('employees.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'super_admin',
        ])->assertRedirect();

        $this->assertSame('super_admin', $admin->fresh()->role);
    }

    public function test_deleting_employee_with_tasks_deactivates_instead_of_deleting(): void
    {
        $super    = $this->makeUser('super_admin');
        $employee = $this->makeUser('employee');
        $task     = Task::create(['title' => 'Keep me', 'created_by' => $employee->id, 'priority' => 'medium']);

        $this->actingAs($super)->delete(route('employees.destroy', $employee))->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'is_active' => false]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_deleting_employee_without_history_removes_them(): void
    {
        $super    = $this->makeUser('super_admin');
        $employee = $this->makeUser('employee');

        $this->actingAs($super)->delete(route('employees.destroy', $employee))->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
    }

    public function test_deactivated_user_with_active_session_is_logged_out(): void
    {
        $employee = $this->makeUser('employee', ['is_active' => false]);

        $this->actingAs($employee)->get(route('profile.show'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->get(route('login'))->assertSee('Your account has been deactivated.');
    }
}
