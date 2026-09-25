<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bitrix "Supposedly completed" is its own status (Reviewing) so tasks continue exactly where they were. */
class ReviewingStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_can_be_reviewing_and_the_active_filter_still_lists_it(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $t = Task::create(['title' => 'Waiting for sign-off', 'created_by' => $admin->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($admin)->patchJson(route('tasks.field', $t), ['field' => 'status', 'value' => 'reviewing'])->assertOk()->assertJson(['success' => true]);
        $this->assertSame('reviewing', $t->fresh()->status);

        $this->actingAs($admin)->get(route('tasks.index', ['status' => 'in_progress']))->assertOk()->assertSee('Waiting for sign-off');
        $this->actingAs($admin)->get(route('tasks.index', ['status' => 'reviewing']))->assertOk()->assertSee('Waiting for sign-off');
    }
}
