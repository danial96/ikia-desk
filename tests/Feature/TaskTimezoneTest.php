<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Support\Tz;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $tz): User
    {
        return User::factory()->create(['role' => 'employee', 'is_active' => true, 'time_zone' => $tz]);
    }

    public function test_a_deadline_typed_in_the_setters_own_timezone_is_stored_as_the_equivalent_karachi_instant(): void
    {
        $rahim = $this->makeUser('Europe/London'); // BST (+1) in September
        $task  = Task::create(['title' => 'T', 'created_by' => $rahim->id, 'priority' => 'medium', 'status' => 'in_progress']);

        // Rahim types "6:00 pm" on his own clock.
        $this->actingAs($rahim)->patchJson(route('tasks.field', $task), [
            'field' => 'deadline', 'value' => '2026-09-28T18:00:00',
        ])->assertOk()->assertJson(['success' => true]);

        // London 18:00 BST (+01:00) is Asia/Karachi (+05:00) 22:00 the same day.
        $stored = $task->fresh()->deadline;
        $this->assertSame('2026-09-28 22:00:00', $stored->format('Y-m-d H:i:s'));
    }

    public function test_two_viewers_in_different_timezones_see_the_same_deadline_as_different_clock_times(): void
    {
        $rahim   = $this->makeUser('Europe/London');
        $huzaifa = $this->makeUser('Asia/Karachi');
        $task    = Task::create(['title' => 'T', 'created_by' => $rahim->id, 'priority' => 'medium', 'status' => 'in_progress']);

        $this->actingAs($rahim)->patchJson(route('tasks.field', $task), [
            'field' => 'deadline', 'value' => '2026-09-28T18:00:00',
        ])->assertOk();
        $task->refresh();

        // Rahim set "6:00 pm" for himself — he should still see 6pm.
        $forRahim = $task->kanbanDeadline($rahim);
        $this->assertStringContainsString('6:00 pm', $forRahim['label']);

        // Huzaifa, in Asia/Karachi, sees the same instant as 10pm his own clock, not 6pm.
        $forHuzaifa = $task->kanbanDeadline($huzaifa);
        $this->assertStringContainsString('10:00 pm', $forHuzaifa['label']);
    }

    public function test_creating_a_task_converts_the_deadline_from_the_creators_timezone(): void
    {
        $rahim = User::factory()->create(['role' => 'admin', 'is_active' => true, 'time_zone' => 'Europe/London']);

        $this->actingAs($rahim)->post(route('tasks.store'), [
            'title'    => 'New task with a deadline',
            'priority' => 'medium',
            'deadline' => '2026-09-28T18:00:00',
        ])->assertRedirect();

        $task = Task::where('title', 'New task with a deadline')->firstOrFail();
        $this->assertSame('2026-09-28 22:00:00', $task->deadline->format('Y-m-d H:i:s'));
    }

    public function test_the_full_edit_form_also_converts_the_deadline(): void
    {
        $rahim = $this->makeUser('Europe/London');
        $task  = Task::create(['title' => 'T', 'created_by' => $rahim->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($rahim)->put(route('tasks.update', $task), [
            'title' => 'T', 'priority' => 'medium', 'status' => 'new',
            'deadline' => '2026-09-28T18:00:00',
        ])->assertRedirect();

        $this->assertSame('2026-09-28 22:00:00', $task->fresh()->deadline->format('Y-m-d H:i:s'));
    }

    public function test_tz_helper_round_trips_correctly(): void
    {
        $london = User::factory()->make(['time_zone' => 'Europe/London']);
        $stored = Tz::toApp('2026-09-28T18:00:00', $london);
        $this->assertSame('2026-09-28 22:00:00', $stored->format('Y-m-d H:i:s')); // canonical Asia/Karachi

        $backToLondon = Tz::forViewer($stored, $london);
        $this->assertSame('2026-09-28 18:00:00', $backToLondon->format('Y-m-d H:i:s'));
    }
}
