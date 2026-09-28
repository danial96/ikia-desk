<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_in_updates_last_login_at_instead_of_leaving_the_stale_bitrix_import_value(): void
    {
        $staleImportDate = now()->subYear();
        $user = User::factory()->create(['is_active' => true, 'last_login_at' => $staleImportDate]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $user->refresh();
        $this->assertNotEquals($staleImportDate->toDateString(), $user->last_login_at->toDateString());
        $this->assertTrue($user->last_login_at->isToday());
    }
}
