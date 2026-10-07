<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactionAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_avatars_in_a_reaction_pill_keep_a_fixed_round_size(): void
    {
        $u    = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        // inside the pill's flex row an avatar must not be squeezed narrower than its height (it turned into an oval)
        $this->assertStringContainsString('flex:0 0 20px;width:20px;height:20px;min-width:20px;max-width:none;box-sizing:border-box;border-radius:50%', $html);
    }
}
