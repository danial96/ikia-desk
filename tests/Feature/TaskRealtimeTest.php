<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaskRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private User $creator, $assignee, $member, $observer, $admin, $outsider;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pusher' => ['app_id' => '3', 'key' => 'k', 'secret' => 's', 'cluster' => 'eu']]);
        Http::fake(['*' => Http::response('{}', 200)]);

        $mk = fn (array $a = []) => User::factory()->create(['is_active' => true] + $a);
        $this->creator  = $mk();
        $this->assignee = $mk();
        $this->member   = $mk();
        $this->observer = $mk();
        $this->admin    = $mk(['role' => 'super_admin']);
        $this->outsider = $mk();

        $this->task = Task::create(['title' => 'T', 'created_by' => $this->creator->id, 'assigned_to' => $this->assignee->id, 'priority' => 'low', 'status' => 'new']);
        $this->task->members()->attach($this->member->id);
        $this->task->observers()->attach($this->observer->id);
    }

    private function taskEvents(): array
    {
        $events = [];
        foreach (Http::recorded() as [$req]) {
            $body = json_decode($req->body(), true);
            if (($body['name'] ?? '') === 'task.changed') {
                $events[] = ['channels' => $body['channels'], 'data' => json_decode($body['data'], true)];
            }
        }
        return $events;
    }

    private function channels(User ...$users): array
    {
        $ids = array_map(fn ($u) => $u->id, $users);
        sort($ids);
        return array_map(fn ($id) => 'private-user.' . $id, $ids);
    }

    public function test_the_audience_is_the_tasks_people_plus_super_admins_and_nobody_else(): void
    {
        $ids = $this->task->audienceIds();
        sort($ids);
        $expected = array_map(fn ($u) => $u->id, [$this->creator, $this->assignee, $this->member, $this->observer, $this->admin]);
        sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertNotContains($this->outsider->id, $ids);
    }

    public function test_a_new_comment_pings_the_audience_with_ids_only(): void
    {
        $this->actingAs($this->member)->postJson(route('api.local.comment', $this->task->id), ['content' => 'private words'])->assertOk();
        app()->terminate();

        $events = $this->taskEvents();
        $this->assertCount(1, $events);
        $channels = $events[0]['channels'];
        sort($channels);
        $this->assertSame($this->channels($this->creator, $this->assignee, $this->member, $this->observer, $this->admin), $channels);
        $this->assertSame(['t' => $this->task->id, 'k' => 'activity', 'by' => $this->member->id], $events[0]['data']);

        Http::assertNotSent(fn (Request $r) => str_contains($r->body(), 'private words'));
    }

    public function test_editing_and_reacting_to_a_comment_ping_too(): void
    {
        $c = $this->task->comments()->create(['user_id' => $this->member->id, 'content' => 'one']);

        $this->actingAs($this->member)->patchJson("/api/local-task/comments/{$c->id}", ['content' => 'two'])->assertOk();
        $this->actingAs($this->creator)->postJson("/api/local-task/comments/{$c->id}/react", ['emoji' => '👍'])->assertOk();
        app()->terminate();

        $kinds = array_map(fn ($e) => $e['data']['k'] . ':' . $e['data']['by'], $this->taskEvents());
        $this->assertContains('comment:' . $this->member->id, $kinds);
        $this->assertContains('comment:' . $this->creator->id, $kinds);
    }

    public function test_changing_a_field_pings_through_the_activity_log(): void
    {
        $this->task->logActivity($this->creator, 'updated', 'status', 'new', 'in_progress');
        app()->terminate();

        $events = $this->taskEvents();
        $this->assertSame('activity', $events[0]['data']['k']);
    }

    public function test_nothing_is_sent_when_realtime_is_not_configured(): void
    {
        config(['services.pusher' => ['app_id' => null, 'key' => null, 'secret' => null, 'cluster' => null]]);
        $this->task->logActivity($this->creator, 'updated', 'status', 'new', 'paused');
        app()->terminate();
        $this->assertSame([], $this->taskEvents());
    }

    public function test_the_task_poll_is_a_slow_backstop_with_a_realtime_listener_and_the_feed_detects_edits(): void
    {
        $js = file_get_contents(resource_path('views/tasks/_task_panel.blade.php'));
        $this->assertStringContainsString("Realtime.skip('tpPoll', 5)", $js);
        $this->assertStringContainsString("addEventListener('rt:task'", $js);
        $this->assertStringContainsString('feedSig !== _pollFeedSig', $js);
        $this->assertStringContainsString("'rt:task'", file_get_contents(resource_path('views/layouts/app.blade.php')));
    }
}
