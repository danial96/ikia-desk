<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Support\MobilePush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobilePushTest extends TestCase
{
    use RefreshDatabase;

    private const EXPO = 'https://exp.host/--/api/v2/push/send';

    private function user(array $a = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true], $a));
    }

    private function phone(User $u, string $token = null, string $platform = 'android'): DeviceToken
    {
        $token ??= 'ExponentPushToken[' . bin2hex(random_bytes(6)) . ']';
        return DeviceToken::create(['user_id' => $u->id, 'push_token_hash' => DeviceToken::hashFor($token), 'push_token' => $token, 'platform' => $platform, 'last_used_at' => now()]);
    }

    private function sent(): array
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::EXPO)->map(fn ($pair) => $pair[0]->data())->flatten(1)->values()->all();
    }

    public function test_a_push_goes_to_every_phone_of_the_person_through_expo(): void
    {
        Http::fake([self::EXPO => Http::response(['data' => [['status' => 'ok'], ['status' => 'ok']]])]);
        $u = $this->user();
        $a = $this->phone($u);
        $b = $this->phone($u, null, 'ios');

        MobilePush::sendToUsers([$u->id], ['title' => 'Sara', 'body' => 'Salam!', 'url' => '/chat?conv=12', 'tag' => 'chat-12', 'type' => 'message']);

        $msgs = $this->sent();
        $this->assertCount(2, $msgs);
        $this->assertEqualsCanonicalizing([$a->push_token, $b->push_token], array_column($msgs, 'to'));
        $this->assertSame('Sara', $msgs[0]['title']);
        $this->assertSame('Salam!', $msgs[0]['body']);
        $this->assertSame('messages', $msgs[0]['channelId']);
        $this->assertSame('chat-12', $msgs[0]['collapseId']);
        $this->assertSame(['url' => '/chat?conv=12', 'type' => 'message', 'conversation_id' => 12], $msgs[0]['data']);
    }

    public function test_task_pushes_carry_the_task_id_and_use_the_tasks_channel(): void
    {
        Http::fake([self::EXPO => Http::response(['data' => [['status' => 'ok']]])]);
        $u = $this->user();
        $this->phone($u);

        MobilePush::sendToUsers([$u->id], ['title' => 'Task', 'body' => 'x', 'url' => '/tasks/kanban?task=77', 'type' => 'task']);

        $m = $this->sent()[0];
        $this->assertSame('tasks', $m['channelId']);
        $this->assertSame(77, $m['data']['task_id']);
    }

    public function test_only_the_named_people_and_only_expo_tokens_are_used(): void
    {
        Http::fake([self::EXPO => Http::response(['data' => [['status' => 'ok']]])]);
        $me = $this->user();
        $other = $this->user();
        $good = $this->phone($me);
        $this->phone($me, 'not-an-expo-token');          // something else a client registered
        $this->phone($other);

        MobilePush::sendToUsers([$me->id], ['title' => 't', 'body' => 'b', 'type' => 'message']);

        $this->assertSame([$good->push_token], array_column($this->sent(), 'to'));
    }

    public function test_nothing_is_sent_to_a_person_without_phones(): void
    {
        Http::fake();
        $u = $this->user();

        MobilePush::sendToUsers([$u->id], ['title' => 't', 'body' => 'b']);

        Http::assertNothingSent();
    }

    public function test_a_voice_call_rings_the_phone_on_its_own_loud_channel_and_names_the_call(): void
    {
        Http::fake([self::EXPO => Http::response(['data' => [['status' => 'ok']]])]);
        $u = $this->user();
        $this->phone($u);

        MobilePush::sendToUsers([$u->id], ['title' => 'Ali', 'body' => 'Incoming voice call', 'url' => '/?call=88', 'tag' => 'call-88', 'type' => 'call', 'ttl' => 45]);

        $m = $this->sent()[0];
        $this->assertSame('calls', $m['channelId']);
        $this->assertSame('high', $m['priority']);
        $this->assertSame(45, $m['ttl']);
        $this->assertSame(88, $m['data']['call_id']);
        $this->assertSame('Incoming voice call', $m['body']);
    }

    public function test_it_can_be_switched_off(): void
    {
        Http::fake();
        config(['services.expo_push.enabled' => false]);
        $u = $this->user();
        $this->phone($u);

        MobilePush::sendToUsers([$u->id], ['title' => 't', 'body' => 'b', 'type' => 'message']);

        Http::assertNothingSent();
    }

    public function test_a_phone_that_expo_says_is_gone_is_forgotten(): void
    {
        $u = $this->user();
        $keep = $this->phone($u);
        $gone = $this->phone($u);
        Http::fake([self::EXPO => function (Request $r) use ($gone) {
            $tickets = array_map(fn ($m) => $m['to'] === $gone->push_token
                ? ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']]
                : ['status' => 'ok'], $r->data());
            return Http::response(['data' => $tickets]);
        }]);

        MobilePush::sendToUsers([$u->id], ['title' => 't', 'body' => 'b', 'type' => 'message']);

        $this->assertDatabaseHas('device_tokens', ['id' => $keep->id]);
        $this->assertDatabaseMissing('device_tokens', ['id' => $gone->id]);
    }

    public function test_the_optional_expo_access_token_is_sent(): void
    {
        Http::fake([self::EXPO => Http::response(['data' => [['status' => 'ok']]])]);
        config(['services.expo_push.access_token' => 'secret-expo']);
        $u = $this->user();
        $this->phone($u);

        MobilePush::sendToUsers([$u->id], ['title' => 't', 'body' => 'b', 'type' => 'message']);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer secret-expo'));
    }

    public function test_more_than_a_hundred_phones_go_out_in_batches(): void
    {
        Http::fake([self::EXPO => Http::response(['data' => []])]);
        $u = $this->user();
        for ($i = 0; $i < 105; $i++) $this->phone($u);

        MobilePush::sendToUsers([$u->id], ['title' => 't', 'body' => 'b', 'type' => 'message']);

        $this->assertCount(105, $this->sent());
        Http::assertSentCount(2);
    }

    public function test_the_detached_command_pipes_the_body_and_keeps_the_text_out_of_the_process_list(): void
    {
        $cmd = MobilePush::detachedCommand('[{"to":"ExponentPushToken[x]","body":"secret text"}]');

        $this->assertStringContainsString('base64 -d', $cmd);
        $this->assertStringContainsString(self::EXPO, $cmd);
        $this->assertStringNotContainsString('secret text', $cmd);
        $this->assertStringContainsString(base64_encode('[{"to":"ExponentPushToken[x]","body":"secret text"}]'), $cmd);
    }

    // ── the places that already pushed to browsers now push to phones too ──

    public function test_a_chat_message_pushes_to_the_other_members_phones_but_not_the_sender(): void
    {
        Http::fake([self::EXPO => Http::response(['data' => [['status' => 'ok']]])]);
        $me = $this->user();
        $alice = $this->user(['name' => 'Alice A']);
        $quiet = $this->user(['notify_messages' => false]);
        $conv = Conversation::create(['type' => 'group', 'name' => 'Team', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $alice->id, $quiet->id]);
        $mine = $this->phone($me);
        $hers = $this->phone($alice);
        $this->phone($quiet);

        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", ['content' => 'meeting at 5'])->assertOk();

        $msgs = $this->sent();
        $this->assertSame([$hers->push_token], array_column($msgs, 'to'));
        $this->assertNotContains($mine->push_token, array_column($msgs, 'to'));
        $this->assertSame('Team', $msgs[0]['title']);
        $this->assertStringContainsString('meeting at 5', $msgs[0]['body']);
        $this->assertSame($conv->id, $msgs[0]['data']['conversation_id']);
    }

    public function test_a_task_notification_pushes_to_the_assignee_phone_when_task_alerts_are_on(): void
    {
        Http::fake([self::EXPO => Http::response(['data' => [['status' => 'ok']]])]);
        $actor = $this->user(['name' => 'Boss']);
        $worker = $this->user();
        $off = $this->user(['notify_tasks' => false]);
        $task = Task::create(['title' => 'Ship it', 'created_by' => $actor->id, 'assigned_to' => $worker->id, 'priority' => 'low', 'status' => 'new']);
        $w = $this->phone($worker);
        $this->phone($off);

        Notification::notify([$worker->id, $off->id], $actor, 'task_assigned', $task, 'Boss assigned you to "Ship it"');

        $msgs = $this->sent();
        $this->assertSame([$w->push_token], array_column($msgs, 'to'));
        $this->assertSame('Ship it', $msgs[0]['title']);
        $this->assertSame('tasks', $msgs[0]['channelId']);
        $this->assertSame($task->id, $msgs[0]['data']['task_id']);
    }
}
