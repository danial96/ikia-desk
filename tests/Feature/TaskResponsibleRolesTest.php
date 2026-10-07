<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Choosing the responsible never makes them a participant; handing the task on leaves the one who had it as an observer. */
class TaskResponsibleRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_task_with_a_responsible_does_not_add_them_as_a_participant(): void
    {
        $owner = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $resp  = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);

        $this->actingAs($owner)->post(route('tasks.store'), [
            'title' => 'Fresh', 'priority' => 'medium', 'assigned_to' => $resp->id, 'members' => [$other->id],
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $task = Task::where('title', 'Fresh')->firstOrFail();
        $this->assertSame($resp->id, $task->assigned_to);
        $this->assertSame([$other->id], $task->members()->pluck('users.id')->all());   // only who was chosen
        $this->assertSame(0, $task->observers()->count());
    }

    public function test_editing_a_task_keeps_participants_exactly_as_chosen_and_the_old_responsible_becomes_observer(): void
    {
        $owner = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $old   = User::factory()->create(['is_active' => true]);
        $new   = User::factory()->create(['is_active' => true]);
        $task  = Task::create(['title' => 'T', 'created_by' => $owner->id, 'assigned_to' => $old->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($owner)->putJson(route('tasks.update', $task), [
            'title' => 'T', 'priority' => 'medium', 'status' => 'new', 'assigned_to' => $new->id, 'members' => [],
        ])->assertOk();

        $task->refresh();
        $this->assertSame($new->id, $task->assigned_to);
        $this->assertSame(0, $task->members()->count());                                  // the new responsible is not forced in as a participant
        $this->assertSame([$old->id], $task->observers()->pluck('users.id')->all());      // the one who handed it on stays as an observer
    }

    public function test_when_the_responsible_hands_the_task_on_they_become_an_observer_and_nobody_becomes_a_participant(): void
    {
        $pm      = User::factory()->create(['is_active' => true]);
        $danial  = User::factory()->create(['is_active' => true]);
        $someone = User::factory()->create(['is_active' => true]);
        $task    = Task::create(['title' => 'T', 'created_by' => $pm->id, 'assigned_to' => $danial->id, 'priority' => 'medium', 'status' => 'new']);

        // the current responsible (not the owner) passes it to someone else, from the task panel
        $this->actingAs($danial)->patchJson(route('tasks.field', $task), ['field' => 'assigned_to', 'value' => $someone->id])->assertOk();

        $task->refresh();
        $this->assertSame($someone->id, $task->assigned_to);
        $this->assertSame([$danial->id], $task->observers()->pluck('users.id')->all());
        $this->assertSame(0, $task->members()->count());
        $this->assertTrue($task->canBeOpenedBy($danial));      // it did not vanish from them
        $this->assertTrue($task->canBeOpenedBy($someone));

        // handed on again: the new former-responsible is kept too, the first observer stays
        $this->actingAs($pm)->patchJson(route('tasks.field', $task), ['field' => 'assigned_to', 'value' => $danial->id])->assertOk();
        $task->refresh();
        $this->assertEqualsCanonicalizing([$danial->id, $someone->id], $task->observers()->pluck('users.id')->all());
    }

    public function test_the_owner_is_not_added_as_an_observer_and_unassigning_adds_only_the_old_responsible(): void
    {
        $pm   = User::factory()->create(['is_active' => true]);
        $dev  = User::factory()->create(['is_active' => true]);
        $task = Task::create(['title' => 'T', 'created_by' => $pm->id, 'assigned_to' => $pm->id, 'priority' => 'medium', 'status' => 'new']);

        $this->actingAs($pm)->patchJson(route('tasks.field', $task), ['field' => 'assigned_to', 'value' => $dev->id])->assertOk();
        $this->assertSame(0, $task->fresh()->observers()->count());     // the owner sees it anyway

        $this->actingAs($pm)->patchJson(route('tasks.field', $task), ['field' => 'assigned_to', 'value' => null])->assertOk();
        $this->assertSame([$dev->id], $task->fresh()->observers()->pluck('users.id')->all());
    }

    public function test_the_kanban_card_shows_and_refreshes_the_responsibles_avatar_after_a_change(): void
    {
        $pm  = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $a   = User::factory()->create(['is_active' => true, 'name' => 'Aaa First']);
        $b   = User::factory()->create(['is_active' => true, 'name' => 'Bbb Second']);
        $task = Task::create(['title' => 'Card', 'created_by' => $pm->id, 'assigned_to' => $a->id, 'priority' => 'medium', 'status' => 'in_progress']);

        // the board draws the avatar inside a slot the page script can refill
        $html = $this->actingAs($pm)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();
        $this->assertStringContainsString('class="kb-assignee"', $html);
        $this->assertStringContainsString("const asg = card.querySelector('.kb-assignee');", $html);
        $this->assertStringContainsString("if (typeof kbUpdateCard === 'function') kbUpdateCard(taskId);", $html);   // panel dropdown refreshes the card

        // and the card refresh endpoint answers with the NEW responsible
        $this->actingAs($pm)->patchJson(route('tasks.field', $task), ['field' => 'assigned_to', 'value' => $b->id])->assertOk();
        $this->actingAs($pm)->getJson("/api/kanban-task/{$task->id}")->assertOk()->assertJsonPath('assignee.name', 'Bbb Second');
    }
}
