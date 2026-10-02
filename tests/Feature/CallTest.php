<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CallTest extends TestCase
{
    use RefreshDatabase;

    private User $a, $b, $c;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pusher' => ['app_id' => '3', 'key' => 'k', 'secret' => 's', 'cluster' => 'eu']]);
        Http::fake(['*' => Http::response('{}', 200)]);
        $this->a = User::factory()->create(['is_active' => true]);
        $this->b = User::factory()->create(['is_active' => true]);
        $this->c = User::factory()->create(['is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Pusher events published so far: [['name'=>..,'channels'=>[..],'data'=>[..]], ...] */
    private function events(?string $name = null): array
    {
        app()->terminate();       // flush the "after response" publishes
        $out = [];
        foreach (Http::recorded() as [$req]) {
            $b = json_decode($req->body(), true);
            if (!isset($b['name']) || !str_contains($req->url(), 'pusher.com')) continue;
            if ($name && $b['name'] !== $name) continue;
            $out[] = ['name' => $b['name'], 'channels' => $b['channels'], 'data' => json_decode($b['data'], true)];
        }
        return $out;
    }

    private function ring(): int
    {
        return $this->actingAs($this->a)->postJson('/api/calls', ['callee_id' => $this->b->id])->assertOk()->json('call.id');
    }

    public function test_calls_are_off_without_realtime(): void
    {
        config(['services.pusher' => ['app_id' => null, 'key' => null, 'secret' => null, 'cluster' => null]]);
        $this->actingAs($this->a)->postJson('/api/calls', ['callee_id' => $this->b->id])->assertNotFound();
        $this->actingAs($this->a)->getJson('/api/calls/ice')->assertNotFound();
    }

    public function test_everything_requires_login(): void
    {
        $this->postJson('/api/calls', ['callee_id' => $this->b->id])->assertUnauthorized();
        $this->postJson('/api/calls/1/signal', ['type' => 'ice', 'data' => ['x' => 1]])->assertUnauthorized();
    }

    public function test_ice_servers_are_stun_by_default_and_add_turn_when_configured(): void
    {
        $r = $this->actingAs($this->a)->getJson('/api/calls/ice')->assertOk()->json('iceServers');
        $this->assertSame(['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'], $r[0]['urls']);
        $this->assertCount(1, $r);

        config(['services.webrtc.turn_urls' => 'turn:t.example.com:3478, turns:t.example.com:5349', 'services.webrtc.turn_username' => 'u', 'services.webrtc.turn_credential' => 'p']);
        $r = $this->actingAs($this->a)->getJson('/api/calls/ice')->json('iceServers');
        $this->assertSame(['urls' => ['turn:t.example.com:3478', 'turns:t.example.com:5349'], 'username' => 'u', 'credential' => 'p'], $r[1]);
    }

    public function test_cloudflare_turn_credentials_are_fetched_once_and_cached(): void
    {
        config(['services.webrtc.cf_turn_key_id' => 'KEY', 'services.webrtc.cf_turn_token' => 'TOK']);
        \Cache::forget('webrtc.cf_ice');
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['rtc.live.cloudflare.com/*' => Http::response(['iceServers' => [
            ['urls' => ['stun:stun.cloudflare.com:3478']],
            ['urls' => ['turn:turn.cloudflare.com:3478?transport=udp'], 'username' => 'cfu', 'credential' => 'cfp'],
        ]], 200), '*' => Http::response('{}', 200)]);

        $r1 = $this->actingAs($this->a)->getJson('/api/calls/ice')->json('iceServers');
        $r2 = $this->actingAs($this->a)->getJson('/api/calls/ice')->json('iceServers');

        $this->assertSame('cfu', $r1[1]['username']);
        $this->assertSame($r1, $r2);
        $cf = collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'cloudflare.com'));
        $this->assertCount(1, $cf);
        $this->assertSame('Bearer TOK', $cf->first()[0]->header('Authorization')[0]);
    }

    public function test_starting_a_call_rings_the_callee_creates_the_chat_and_tells_both_people(): void
    {
        $this->assertSame(0, Conversation::count());
        $id = $this->ring();

        $call = Call::find($id);
        $this->assertSame('ringing', $call->status);
        $this->assertNotNull($call->conversation_id);
        $this->assertSame('direct', $call->conversation->type ?? Conversation::find($call->conversation_id)->type);

        $ev = $this->events('call.state');
        $this->assertCount(1, $ev);
        $ch = $ev[0]['channels']; sort($ch);
        $this->assertSame(['private-user.' . $this->a->id, 'private-user.' . $this->b->id], $ch);
        $this->assertSame('ringing', $ev[0]['data']['status']);
        $this->assertSame($this->a->name, $ev[0]['data']['caller']['name']);
        $this->assertSame($this->b->id, $ev[0]['data']['callee']['id']);
    }

    public function test_starting_a_call_reuses_the_existing_direct_chat(): void
    {
        $conv = Conversation::create(['type' => 'direct', 'created_by' => $this->a->id]);
        $conv->members()->attach([$this->a->id, $this->b->id]);

        $this->assertSame($conv->id, Call::find($this->ring())->conversation_id);
        $this->assertSame(1, Conversation::count());
    }

    public function test_an_incoming_call_also_reaches_a_closed_browser_as_a_push_that_expires_quickly(): void
    {
        [$pub, $priv] = WebPush::generateKeys();
        config(['services.webpush' => ['public_key' => $pub, 'private_key' => $priv, 'subject' => 'mailto:t@example.com']]);
        [$bPub] = WebPush::generateKeys();
        PushSubscription::create(['user_id' => $this->b->id, 'endpoint_hash' => PushSubscription::hashFor('https://push.example.com/x'),
            'endpoint' => 'https://push.example.com/x', 'p256dh' => $bPub, 'auth' => WebPush::b64u(random_bytes(16))]);

        $id = $this->ring();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://push.example.com/x' && $r->header('TTL')[0] === '45' && $r->header('Urgency')[0] === 'high');
        $this->assertGreaterThan(0, $id);
    }

    public function test_you_cannot_call_yourself_or_someone_deactivated(): void
    {
        $this->actingAs($this->a)->postJson('/api/calls', ['callee_id' => $this->a->id])->assertStatus(422);
        $off = User::factory()->create(['is_active' => false]);
        $this->actingAs($this->a)->postJson('/api/calls', ['callee_id' => $off->id])->assertStatus(422);
        $this->assertSame(0, Call::count());
    }

    public function test_a_busy_person_cannot_be_rung_and_a_busy_caller_cannot_start_another(): void
    {
        $this->ring();       // a -> b, ringing

        $this->actingAs($this->c)->postJson('/api/calls', ['callee_id' => $this->b->id])
            ->assertStatus(409)->assertJsonPath('code', 'busy');
        $this->actingAs($this->a)->postJson('/api/calls', ['callee_id' => $this->c->id])
            ->assertStatus(409)->assertJsonPath('code', 'self_busy');
        $this->assertSame(1, Call::count());
    }

    public function test_only_the_callee_can_accept_and_only_while_it_rings(): void
    {
        $id = $this->ring();

        $this->actingAs($this->a)->postJson("/api/calls/$id/accept")->assertForbidden();
        $this->actingAs($this->c)->postJson("/api/calls/$id/accept")->assertForbidden();

        $this->actingAs($this->b)->postJson("/api/calls/$id/accept")->assertOk()->assertJsonPath('call.status', 'active');
        $this->assertNotNull(Call::find($id)->answered_at);

        $this->actingAs($this->b)->postJson("/api/calls/$id/accept")->assertStatus(409);      // already answered
    }

    public function test_an_answered_call_is_logged_in_the_chat_with_its_length_for_both_people(): void
    {
        Carbon::setTestNow('2026-10-03 10:00:00');
        $id = $this->ring();
        $this->actingAs($this->b)->postJson("/api/calls/$id/accept")->assertOk();

        foreach (['10:00:25', '10:00:50', '10:01:15', '10:01:40', '10:02:05', '10:02:30'] as $t) {   // the browsers' heartbeat
            Carbon::setTestNow("2026-10-03 $t");
            $this->actingAs($this->b)->postJson("/api/calls/$id/ping")->assertOk();
        }
        Carbon::setTestNow('2026-10-03 10:02:31');
        $this->actingAs($this->a)->postJson("/api/calls/$id/end")->assertOk()->assertJsonPath('call.status', 'ended')->assertJsonPath('call.duration', 151);

        $msg = Message::latest('id')->first();
        $this->assertSame('📞 Voice call · 2:31', $msg->content);
        $this->assertSame($this->a->id, $msg->user_id);
        $this->assertSame(Call::find($id)->conversation_id, $msg->conversation_id);

        $this->assertNotEmpty(array_filter($this->events('chat.changed'), fn ($e) => $e['data']['k'] === 'new' && $e['data']['m'] === $msg->id));
        $this->assertSame(0, Notification::where('user_id', $this->b->id)->count());     // it was answered: no "missed" bell
    }

    public function test_a_call_the_caller_cancels_is_a_missed_call_with_a_bell_for_the_callee(): void
    {
        $id = $this->ring();
        $this->actingAs($this->a)->postJson("/api/calls/$id/end")->assertOk()->assertJsonPath('call.status', 'missed');

        $this->assertSame('📞 Missed voice call', Message::latest('id')->first()->content);
        $n = Notification::where('user_id', $this->b->id)->first();
        $this->assertStringContainsString('Missed voice call from ' . $this->a->name, $n->message);
    }

    public function test_declining_is_recorded_and_both_browsers_hear_about_it(): void
    {
        $id = $this->ring();
        $this->actingAs($this->b)->postJson("/api/calls/$id/end")->assertOk()->assertJsonPath('call.status', 'declined');

        $states = array_map(fn ($e) => $e['data']['status'], $this->events('call.state'));
        $this->assertSame(['ringing', 'declined'], $states);
        $this->assertSame('📞 Missed voice call', Message::latest('id')->first()->content);
    }

    public function test_ending_twice_does_not_log_twice(): void
    {
        $id = $this->ring();
        $this->actingAs($this->a)->postJson("/api/calls/$id/end")->assertOk();
        $this->actingAs($this->b)->postJson("/api/calls/$id/end")->assertOk();

        $this->assertSame(1, Message::count());
        $this->assertSame('missed', Call::find($id)->status);      // the second tap did not rewrite history
    }

    public function test_signals_go_only_to_the_other_person_and_only_for_live_calls(): void
    {
        $id = $this->ring();
        $this->actingAs($this->b)->postJson("/api/calls/$id/accept");

        $this->actingAs($this->a)->postJson("/api/calls/$id/signal", ['type' => 'offer', 'data' => ['type' => 'offer', 'sdp' => 'v=0...']])->assertOk();
        $this->actingAs($this->b)->postJson("/api/calls/$id/signal", ['type' => 'ice', 'data' => ['candidate' => 'candidate:1 1 udp 1 1.2.3.4 5 typ host']])->assertOk();

        $sigs = $this->events('call.signal');
        $this->assertCount(2, $sigs);
        $this->assertSame(['private-user.' . $this->b->id], $sigs[0]['channels']);       // a's offer -> b
        $this->assertSame('offer', $sigs[0]['data']['type']);
        $this->assertSame($this->a->id, $sigs[0]['data']['from']);
        $this->assertSame(['private-user.' . $this->a->id], $sigs[1]['channels']);       // b's candidate -> a

        $this->actingAs($this->a)->postJson("/api/calls/$id/end");
        $this->actingAs($this->a)->postJson("/api/calls/$id/signal", ['type' => 'ice', 'data' => ['x' => 1]])->assertStatus(409);
    }

    public function test_signals_are_validated_and_closed_to_outsiders(): void
    {
        $id = $this->ring();

        $this->actingAs($this->c)->postJson("/api/calls/$id/signal", ['type' => 'ice', 'data' => ['x' => 1]])->assertForbidden();
        $this->actingAs($this->a)->postJson("/api/calls/$id/signal", ['type' => 'bogus', 'data' => ['x' => 1]])->assertStatus(422);
        $this->actingAs($this->a)->postJson("/api/calls/$id/signal", ['type' => 'offer'])->assertStatus(422);
        $this->actingAs($this->a)->postJson("/api/calls/$id/signal", ['type' => 'offer', 'data' => ['sdp' => str_repeat('x', 21000)]])->assertStatus(422);
        $this->assertSame([], $this->events('call.signal'));
    }

    public function test_only_the_two_people_on_a_call_can_look_at_it(): void
    {
        $id = $this->ring();
        $this->actingAs($this->c)->getJson("/api/calls/$id")->assertForbidden();
        $this->actingAs($this->b)->getJson("/api/calls/$id")->assertOk()->assertJsonPath('call.status', 'ringing');
    }

    public function test_a_call_nobody_answered_expires_and_stops_blocking_new_calls(): void
    {
        Carbon::setTestNow('2026-10-03 10:00:00');
        $id = $this->ring();

        Carbon::setTestNow('2026-10-03 10:01:30');                       // > 60s of ringing: both browsers must have vanished
        $this->actingAs($this->c)->postJson('/api/calls', ['callee_id' => $this->b->id])->assertOk();

        $this->assertSame('missed', Call::find($id)->status);
        $this->assertSame('📞 Missed voice call', Message::where('user_id', $this->a->id)->latest('id')->first()->content);
    }

    public function test_an_answered_call_whose_browsers_stopped_pinging_is_closed(): void
    {
        Carbon::setTestNow('2026-10-03 10:00:00');
        $id = $this->ring();
        $this->actingAs($this->b)->postJson("/api/calls/$id/accept");

        Carbon::setTestNow('2026-10-03 10:00:20');
        $this->actingAs($this->a)->postJson("/api/calls/$id/ping")->assertOk()->assertJsonPath('status', 'active');
        $this->assertSame('active', Call::find($id)->status);            // pinged: still alive

        Carbon::setTestNow('2026-10-03 10:02:30');                       // 130s of silence
        $this->actingAs($this->b)->getJson("/api/calls/$id")->assertJsonPath('call.status', 'ended');
        $this->assertSame('📞 Voice call · 0:20', Message::latest('id')->first()->content);
    }

    public function test_the_call_window_is_a_standalone_page_that_only_opens_with_realtime_and_login(): void
    {
        $this->get('/call/window')->assertRedirect();                       // guests go to login

        $html = $this->actingAs($this->a)->get('/call/window?mode=start&uid=' . $this->b->id)->assertOk()->getContent();
        $this->assertStringContainsString('window.CALL_POPUP = true', $html);
        $this->assertStringContainsString('id="call-root"', $html);
        $this->assertStringContainsString("ch.bind('call.signal'", $html);
        $this->assertStringContainsString('private-user.' . $this->a->id, $html);        // its own channel, nobody else's
        $this->assertStringNotContainsString('chat-panel', $html);                     // not the whole app shell

        config(['services.pusher' => ['app_id' => null, 'key' => null, 'secret' => null, 'cluster' => null]]);
        $this->actingAs($this->a)->get('/call/window')->assertNotFound();
    }

    public function test_the_channel_prefix_keeps_a_dev_machine_off_production_channels(): void
    {
        config(['services.pusher.channel_prefix' => 'e2e-']);

        $this->assertSame('private-e2e-user.7', \App\Support\Realtime::userChannel(7));
        $this->actingAs($this->a)->postJson('/realtime/auth', ['socket_id' => '1.2', 'channel_name' => 'private-e2e-user.' . $this->a->id])->assertOk();
        $this->actingAs($this->a)->postJson('/realtime/auth', ['socket_id' => '1.2', 'channel_name' => 'private-user.' . $this->a->id])->assertForbidden();
        $this->assertStringContainsString('private-e2e-user.' . $this->a->id,
            $this->actingAs($this->a)->get('/call/window')->getContent());
    }

    public function test_the_phone_button_and_module_are_wired_into_the_pages(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($user)->get('/chat')->assertOk()->getContent();

        $this->assertStringContainsString('id="cp-call-btn"', $html);
        $this->assertStringContainsString('id="chat-call-btn"', $html);
        $this->assertStringContainsString("ch.bind('call.signal'", $html);
        $this->assertStringContainsString('window.Call = {', $html);

        config(['services.pusher' => ['app_id' => null, 'key' => null, 'secret' => null, 'cluster' => null]]);
        $off = $this->actingAs($user)->get('/chat')->getContent();
        $this->assertStringNotContainsString('window.Call = {', $off);      // no realtime -> no calling UI
    }
}
