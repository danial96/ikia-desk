<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_a_deadline_keeps_the_picked_date(): void
    {
        config(['app.timezone' => 'Asia/Karachi']);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $task  = Task::create(['title' => 'T', 'created_by' => $admin->id, 'priority' => 'medium']);

        // The calendar picker now sends a naive local datetime (no UTC 'Z')
        $res = $this->actingAs($admin)->patchJson(route('tasks.field', $task), [
            'field' => 'deadline', 'value' => '2026-09-25T09:00:00',
        ])->assertOk();

        $this->assertSame('2026-09-25 09:00', $task->fresh()->deadline->format('Y-m-d H:i'));
        // The value echoed back to the client is the same day, not a day behind
        $this->assertStringStartsWith('2026-09-25', $res->json('deadline_raw'));
    }

    public function test_early_morning_deadline_does_not_shift_a_day(): void
    {
        config(['app.timezone' => 'Asia/Karachi']);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $task  = Task::create(['title' => 'T2', 'created_by' => $admin->id, 'priority' => 'medium']);

        // 2 AM used to roll back to the previous day under the old UTC conversion
        $this->actingAs($admin)->patchJson(route('tasks.field', $task), [
            'field' => 'deadline', 'value' => '2026-09-25T02:00:00',
        ])->assertOk();

        $this->assertSame('2026-09-25', $task->fresh()->deadline->format('Y-m-d'));
    }
}
