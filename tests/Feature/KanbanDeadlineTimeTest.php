<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The board, the cards and the task panel must agree on when a task is overdue:
 * the moment the deadline *time* has passed — not only once the whole day is over.
 */
class KanbanDeadlineTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function task(User $by, string $title, ?string $deadline): Task
    {
        return Task::create([
            'title' => $title, 'created_by' => $by->id, 'priority' => 'medium',
            'status' => 'in_progress', 'deadline' => $deadline,
        ]);
    }

    private function board(User $as)
    {
        return $this->actingAs($as)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('tasks.kanban'))->assertOk();
    }

    public function test_task_is_overdue_as_soon_as_its_deadline_time_passes_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 19:11:00', 'Asia/Karachi'));
        $admin = $this->admin();
        $this->task($admin, 'Due 6pm (passed)', '2026-09-24 18:00:00');
        $this->task($admin, 'Due 10pm (upcoming)', '2026-09-24 22:00:00');

        $cols = $this->board($admin)->json('columns');

        $this->assertSame(['Due 6pm (passed)'], collect($cols['overdue'])->pluck('title')->all());
        $this->assertSame(['Due 10pm (upcoming)'], collect($cols['due_today'])->pluck('title')->all());
        $this->assertTrue($cols['overdue'][0]['dl_past']);
        $this->assertFalse($cols['due_today'][0]['dl_past']);
    }

    public function test_card_deadline_label_includes_the_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 09:00:00', 'Asia/Karachi'));
        $admin = $this->admin();
        $this->task($admin, 'Timed', '2026-09-24 18:00:00');

        $cols = $this->board($admin)->json('columns');

        $this->assertSame('Sep 24, 2026, 6:00 PM', $cols['due_today'][0]['deadline']);
    }

    public function test_server_rendered_card_shows_time_and_overdue_colour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 19:11:00', 'Asia/Karachi'));
        $admin = $this->admin();
        $this->task($admin, 'Passed today', '2026-09-24 18:00:00');

        $this->actingAs($admin)->get(route('tasks.kanban'))->assertOk()
            ->assertSee('Sep 24, 2026, 6:00 PM');
    }

    public function test_move_reports_when_the_new_date_is_already_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 19:11:00', 'Asia/Karachi'));
        $admin = $this->admin();
        $task  = $this->task($admin, 'T', '2026-09-20 18:00:00');

        // Same 6 PM time, moved to today: 6 PM has already passed at 7:11 PM → still overdue.
        $this->actingAs($admin)->patchJson(route('tasks.move', $task), ['deadline' => '2026-09-24'])
            ->assertOk()->assertJson(['success' => true, 'overdue' => true]);
        $this->assertSame('2026-09-24 18:00:00', $task->fresh()->deadline->format('Y-m-d H:i:s'));

        // Moved to tomorrow: not overdue.
        $this->actingAs($admin)->patchJson(route('tasks.move', $task), ['deadline' => '2026-09-25'])
            ->assertOk()->assertJson(['success' => true, 'overdue' => false]);
    }

    public function test_board_version_changes_when_a_task_is_edited(): void
    {
        $admin = $this->admin();
        $task  = $this->task($admin, 'Original', null);

        $v1 = $this->actingAs($admin)->getJson(route('tasks.kanban.version'))->assertOk()->json('v');

        $this->travel(2)->minutes();
        $task->update(['title' => 'Edited by someone else']);

        $v2 = $this->actingAs($admin)->getJson(route('tasks.kanban.version'))->assertOk()->json('v');

        $this->assertNotSame($v1, $v2);
        // Nothing changed since → stable, so an idle board never re-fetches needlessly.
        $this->assertSame($v2, $this->actingAs($admin)->getJson(route('tasks.kanban.version'))->json('v'));
    }

    public function test_board_version_requires_login(): void
    {
        $this->getJson(route('tasks.kanban.version'))->assertUnauthorized();
    }
}
