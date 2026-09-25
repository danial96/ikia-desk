<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_pick_a_theme_and_it_is_applied_to_every_page(): void
    {
        $u = User::factory()->create(['is_active' => true]);

        $this->actingAs($u)->postJson(route('theme.set'), ['theme' => 'sunset'])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('sunset', $u->fresh()->theme);
        $this->actingAs($u)->get(route('profile.show'))->assertOk()->assertSee('#bg-canvas{background:linear-gradient(160deg,#1b1147', false);

        $this->actingAs($u)->postJson(route('theme.set'), ['theme' => 'nope'])->assertStatus(422);
        $this->actingAs($u)->postJson(route('theme.set'), ['theme' => 'default'])->assertOk();
        $this->assertNull($u->fresh()->theme);
    }

    public function test_profile_lists_supervisor_and_subordinates_from_departments(): void
    {
        $boss = User::factory()->create(['is_active' => true, 'name' => 'Boss Person', 'department' => 'Production']);
        $me   = User::factory()->create(['is_active' => true, 'name' => 'Middle Person', 'department' => 'Frontend']);
        $jr   = User::factory()->create(['is_active' => true, 'name' => 'Junior Person', 'department' => 'Frontend']);
        $prod = Department::create(['name' => 'Production', 'head_id' => $boss->id]);
        Department::create(['name' => 'Frontend', 'parent_id' => $prod->id, 'head_id' => $me->id]);

        $admin = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $this->actingAs($admin)->get(route('profile.show.user', $me->id))->assertOk()
            ->assertSee('Supervisor')->assertSee('Boss Person')->assertSee('Subordinates')->assertSee('Junior Person');
    }
}
