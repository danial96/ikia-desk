<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfTokenEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_token_and_the_login_state(): void
    {
        $this->getJson('/csrf-token')->assertOk()->assertJson(['auth' => false])->assertJsonStructure(['token']);

        $u = User::factory()->create(['is_active' => true]);
        $this->actingAs($u)->getJson('/csrf-token')->assertOk()->assertJson(['auth' => true]);
    }
}
