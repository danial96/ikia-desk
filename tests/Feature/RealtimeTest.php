<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config(['services.pusher' => ['app_id' => '3', 'key' => '278d425bdf160c739803', 'secret' => '7ad3773142a6692b25b8', 'cluster' => 'eu']]);
    }

    private function directConv(): array
    {
        $me    = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $conv  = Conversation::create(['type' => 'direct', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $other->id]);
        return [$me, $other, $conv];
    }

    public function test_it_is_off_until_all_four_settings_exist(): void
    {
        $this->assertFalse(Realtime::enabled());
        $this->assertNull(Realtime::clientConfig());

        config(['services.pusher' => ['app_id' => '3', 'key' => 'k', 'secret' => 's', 'cluster' => null]]);
        $this->assertFalse(Realtime::enabled());

        $this->configure();
        $this->assertTrue(Realtime::enabled());
        $this->assertSame(['key' => '278d425bdf160c739803', 'cluster' => 'eu'], Realtime::clientConfig());
    }

    public function test_channel_auth_token_matches_the_pusher_documentation_example(): void
    {
        $this->configure();
        $this->assertSame(
            '278d425bdf160c739803:58df8b0c36d6982b82c3ecf6b4662e34fe8c25bba48f5369f135bf843651c3a4',
            Realtime::authToken('1234.1234', 'private-foobar')
        );
    }

    public function test_auth_endpoint_only_signs_the_callers_own_channel(): void
    {
        $this->configure();
        $me    = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);

        $ok = $this->actingAs($me)->postJson('/realtime/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-user.' . $me->id]);
        $ok->assertOk()->assertJson(['auth' => Realtime::authToken('1234.5678', 'private-user.' . $me->id)]);

        // someone else's channel, an arbitrary channel, and a malformed socket id are all refused
        $this->actingAs($me)->postJson('/realtime/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-user.' . $other->id])->assertForbidden();
        $this->actingAs($me)->postJson('/realtime/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-admin'])->assertForbidden();
        $this->actingAs($me)->postJson('/realtime/auth', ['socket_id' => 'nope', 'channel_name' => 'private-user.' . $me->id])->assertStatus(422);
    }

    public function test_auth_endpoint_requires_login_and_is_404_when_unconfigured(): void
    {
        $this->postJson('/realtime/auth', ['socket_id' => '1.2', 'channel_name' => 'private-user.1'])->assertUnauthorized();

        $me = User::factory()->create(['is_active' => true]);
        $this->actingAs($me)->postJson('/realtime/auth', ['socket_id' => '1.2', 'channel_name' => 'private-user.' . $me->id])->assertNotFound();
    }

    public function test_publish_posts_a_correctly_signed_event_to_each_users_private_channel(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response('{}', 200)]);

        Realtime::publishNow([5, 6, 5], 'chat.changed', ['c' => 9, 'k' => 'new']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $req) {
            parse_str(parse_url($req->url(), PHP_URL_QUERY), $q);
            $path  = parse_url($req->url(), PHP_URL_PATH);
            $given = $q['auth_signature'];
            unset($q['auth_signature']);
            ksort($q);
            $expected = hash_hmac('sha256', "POST\n$path\n" . urldecode(http_build_query($q)), '7ad3773142a6692b25b8');
            $body = json_decode($req->body(), true);

            return str_starts_with($req->url(), 'https://api-eu.pusher.com/apps/3/events?')
                && $path === '/apps/3/events'
                && $q['auth_key'] === '278d425bdf160c739803'
                && $q['body_md5'] === md5($req->body())
                && hash_equals($expected, $given)
                && $body['name'] === 'chat.changed'
                && $body['channels'] === ['private-user.5', 'private-user.6']          // de-duplicated
                && json_decode($body['data'], true) === ['c' => 9, 'k' => 'new'];
        });
    }

    public function test_publish_never_throws_when_pusher_is_down(): void
    {
        $this->configure();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('boom'));

        Realtime::publishNow([1], 'notif');
        $this->assertTrue(true);   // reaching here is the assertion
    }

    public function test_nothing_is_sent_when_not_configured(): void
    {
        Http::fake();
        Realtime::publishToUsers([1, 2], 'notif');
        Realtime::publishNow([1, 2], 'notif');
        Http::assertNothingSent();
    }

    public function test_sending_a_chat_message_pushes_a_new_message_signal_to_every_member_with_ids_only(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response('{}', 200)]);
        [$me, $other, $conv] = $this->directConv();

        $res = $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", ['content' => 'super secret text'])->assertOk();

        Http::assertSent(function (Request $req) use ($me, $other, $conv, $res) {
            $body = json_decode($req->body(), true);
            $data = json_decode($body['data'], true);
            return $body['name'] === 'chat.changed'
                && $body['channels'] === ['private-user.' . $me->id, 'private-user.' . $other->id]
                && $data === ['c' => $conv->id, 'k' => 'new', 'm' => $res->json('message.id'), 'by' => $me->id]
                && !str_contains($req->body(), 'super secret text');         // the text never goes through Pusher
        });
    }

    public function test_opening_a_conversation_tells_the_sender_it_was_read_but_only_when_something_was_unread(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response('{}', 200)]);
        [$me, $other, $conv] = $this->directConv();
        Message::create(['conversation_id' => $conv->id, 'user_id' => $other->id, 'content' => 'hi']);

        $this->actingAs($me)->getJson("/api/chat/convs/{$conv->id}/msgs")->assertOk();
        Http::assertSent(function (Request $req) use ($other, $conv) {
            $body = json_decode($req->body(), true);
            $data = json_decode($body['data'], true);
            return $body['channels'] === ['private-user.' . $other->id] && $data['k'] === 'read' && $data['c'] === $conv->id;
        });

        // opening it again with nothing new to read sends nothing more (still just the one from before)
        $this->actingAs($me)->getJson("/api/chat/convs/{$conv->id}/msgs")->assertOk();
        Http::assertSentCount(1);
    }

    public function test_edit_delete_and_react_push_to_the_conversation_members(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response('{}', 200)]);
        [$me, $other, $conv] = $this->directConv();
        $msg = Message::create(['conversation_id' => $conv->id, 'user_id' => $me->id, 'content' => 'one']);

        $kinds = [];
        $collect = function () use (&$kinds) {
            foreach (Http::recorded() as [$req]) {
                $kinds[] = json_decode(json_decode($req->body(), true)['data'], true)['k'];
            }
        };

        $this->actingAs($me)->patchJson("/api/chat/msgs/{$msg->id}", ['content' => 'two'])->assertOk();
        $this->actingAs($other)->postJson("/api/chat/msgs/{$msg->id}/react", ['emoji' => '👍'])->assertOk();
        $this->actingAs($me)->deleteJson("/api/chat/msgs/{$msg->id}?scope=everyone")->assertOk();
        $collect();

        $this->assertSame(['edit', 'react', 'delete'], $kinds);
    }

    public function test_notifications_push_a_notif_signal_to_the_people_who_got_one(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response('{}', 200)]);
        $actor = User::factory()->create(['is_active' => true]);
        $a     = User::factory()->create(['is_active' => true]);
        $b     = User::factory()->create(['is_active' => true]);

        \App\Models\Notification::mention([$a->id, $b->id, $actor->id], $actor, 'hey');
        app()->terminate();

        Http::assertSent(function (Request $req) use ($a, $b) {
            $body = json_decode($req->body(), true);
            return $body['name'] === 'notif' && $body['channels'] === ['private-user.' . $a->id, 'private-user.' . $b->id];
        });
    }
}
