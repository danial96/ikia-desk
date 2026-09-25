<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_the_bitrix_style_view_and_keeps_the_edit_form(): void
    {
        $u = User::factory()->create(['is_active' => true, 'position' => 'CMS Manager', 'gender' => 'M', 'last_seen_at' => now()]);

        $this->actingAs($u)->get(route('profile.show'))->assertOk()
            ->assertSee('Contact information')->assertSee('CMS Manager')->assertSee('Male')->assertSee('ONLINE')
            ->assertSee('name="phone"', false)->assertSee('name="password"', false);
    }
}
