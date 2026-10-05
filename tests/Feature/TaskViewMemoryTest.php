<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskViewMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chosen_tasks_layout_survives_logging_in_again(): void
    {
        $u = User::factory()->create(['is_active' => true]);

        $this->actingAs($u)->get(route('tasks.kanban'))->assertRedirect(route('tasks.kanban', ['status' => 'in_progress']));
        $this->assertSame('kanban', $u->fresh()->task_view);

        // a brand-new session (like after logging in again): the list URL sends them to the board
        $this->flushSession();
        $this->actingAs($u->fresh())->get(route('tasks.index'))->assertRedirect(route('tasks.kanban', ['status' => 'in_progress']));   // one hop, default filter included
        $this->actingAs($u->fresh())->get(route('dashboard'))->assertSee(route('tasks.kanban'), false);   // sidebar link opens the board

        // choosing List explicitly switches (and remembers) it
        $this->actingAs($u->fresh())->get(route('tasks.index', ['view' => 'list']))->assertRedirect();
        $this->assertSame('list', $u->fresh()->task_view);
        $this->actingAs($u->fresh())->get(route('tasks.index'))->assertRedirect(route('tasks.index', ['status' => 'in_progress']));
    }

    public function test_the_site_root_sends_signed_in_people_straight_to_their_landing_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));

        $list = User::factory()->create(['is_active' => true, 'task_view' => 'list']);
        $this->actingAs($list)->get('/')->assertRedirect(route('tasks.index', ['status' => 'in_progress']));

        $board = User::factory()->create(['is_active' => true, 'task_view' => 'kanban']);
        $this->actingAs($board)->get('/')->assertRedirect(route('tasks.kanban', ['status' => 'in_progress']));
    }
}
