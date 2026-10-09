<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTerminalAccessTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://securepaymentterminal.digitalservicescorp.com/';

    private function user(string $role = 'employee', array $perms = []): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true, 'permissions' => $perms]);
    }

    public function test_only_people_with_the_permission_and_the_super_admin_see_the_tab_and_get_through(): void
    {
        $plain = $this->user();
        $page  = $this->actingAs($plain)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Payment Terminal', $page);
        $this->assertStringNotContainsString('securepaymentterminal', $page);          // the address never reaches people without access
        $this->actingAs($plain)->get(route('payment.terminal'))->assertForbidden();

        $allowed = $this->user('employee', ['access_payment_terminal' => true]);
        $page = $this->actingAs($allowed)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Payment Terminal', $page);
        $this->assertStringContainsString(route('payment.terminal'), $page);
        $this->actingAs($allowed)->get(route('payment.terminal'))->assertRedirect(self::URL)->assertHeader('Referrer-Policy', 'no-referrer');

        // a plain admin is not let in automatically: it has to be switched on for them like for anyone else
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('payment.terminal'))->assertForbidden();
        $admin2 = $this->user('admin', ['access_payment_terminal' => true]);
        $this->actingAs($admin2)->get(route('payment.terminal'))->assertRedirect(self::URL);

        $super = $this->user('super_admin');
        $this->actingAs($super)->get(route('payment.terminal'))->assertRedirect(self::URL);
    }

    public function test_an_admin_can_switch_it_on_from_the_permissions_page(): void
    {
        $admin    = $this->user('super_admin');
        $employee = $this->user();

        $page = $this->actingAs($admin)->get(route('permissions.index'))->assertOk()->getContent();
        $this->assertStringContainsString('name="access_payment_terminal"', $page);

        $this->actingAs($admin)->post(route('permissions.update', $employee), ['access_payment_terminal' => '1'])->assertRedirect();
        $this->assertTrue($employee->fresh()->canAccessPaymentTerminal());

        $this->actingAs($admin)->post(route('permissions.update', $employee), [])->assertRedirect();
        $this->assertFalse($employee->fresh()->canAccessPaymentTerminal());
    }
}
