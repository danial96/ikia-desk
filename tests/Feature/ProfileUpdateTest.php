<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_employee_can_save_their_own_profile_without_touching_email(): void
    {
        // The "email" field is rendered as a read-only div (no name attribute) for anyone who
        // isn't an admin, so the browser never submits it — this used to fail with a
        // "the email field is required" error on every save.
        $user = User::factory()->create(['is_active' => true, 'role' => 'employee', 'email' => 'user@example.com', 'name' => 'Old Name']);

        $this->actingAs($user)->post(route('profile.update'), [
            'name'  => 'New Name',
            'phone' => '03001234567',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('03001234567', $user->phone);
        $this->assertSame('user@example.com', $user->email); // untouched
    }

    public function test_a_regular_employee_cannot_change_their_own_email_position_or_department_by_crafting_the_request(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'employee', 'email' => 'user@example.com', 'position' => 'Developer', 'department' => 'Engineering']);

        $this->actingAs($user)->post(route('profile.update'), [
            'name'       => $user->name,
            'email'      => 'hijacked@example.com',
            'position'   => 'CEO',
            'department' => 'Executive',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('user@example.com', $user->email);
        $this->assertSame('Developer', $user->position);
        $this->assertSame('Engineering', $user->department);
    }

    public function test_an_admin_can_still_update_their_own_email(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin', 'email' => 'admin@example.com']);

        $this->actingAs($admin)->post(route('profile.update'), [
            'name'  => $admin->name,
            'email' => 'admin-new@example.com',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('admin-new@example.com', $admin->fresh()->email);
    }
}
