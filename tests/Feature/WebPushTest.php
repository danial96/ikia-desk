<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\Task;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private array $browser;   // a pretend subscriber: keys a real browser would hold

    protected function setUp(): void
    {
        parent::setUp();
        [$pub, $priv] = WebPush::generateKeys();
        config(['services.webpush' => ['public_key' => $pub, 'private_key' => $priv, 'subject' => 'mailto:test@example.com']]);

        [$bPub, $bPriv] = WebPush::generateKeys();
        $this->browser = ['public' => $bPub, 'private' => $bPriv, 'auth' => WebPush::b64u(random_bytes(16))];
    }

    private function subscribe(User $u, string $endpoint = 'https://push.example.com/send/abc'): PushSubscription
    {
        return PushSubscription::create([
            'user_id' => $u->id, 'endpoint_hash' => PushSubscription::hashFor($endpoint), 'endpoint' => $endpoint,
            'p256dh' => $this->browser['public'], 'auth' => $this->browser['auth'],
        ]);
    }

    private function decryptSent(Request $req): array
    {
        return json_decode(WebPush::decrypt(
            $req->body(),
            WebPush::b64uDecode($this->browser['private']),
            WebPush::b64uDecode($this->browser['public']),
            WebPush::b64uDecode($this->browser['auth'])
        ), true);
    }

    public function test_it_is_off_without_vapid_keys(): void
    {
        config(['services.webpush' => ['public_key' => null, 'private_key' => null]]);
        Http::fake();
        $u = User::factory()->create(['is_active' => true]);

        $this->assertFalse(WebPush::enabled());
        WebPush::sendToUsers([$u->id], ['title' => 'x']);
        Http::assertNothingSent();
        $this->actingAs($u)->postJson('/push/subscribe', ['endpoint' => 'https://a.b/c', 'keys' => ['p256dh' => 'x', 'auth' => 'y']])->assertNotFound();
    }

    public function test_encryption_matches_the_rfc_8291_test_vector(): void
    {
        $body = WebPush::encrypt(
            'When I grow up, I want to be a watermelon',
            WebPush::b64uDecode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
            WebPush::b64uDecode('BTBZMqHH6r4Tts7J_aSIgg'),
            [
                'private' => WebPush::b64uDecode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'),
                'public'  => WebPush::b64uDecode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8'),
                'salt'    => WebPush::b64uDecode('DGv6ra1nlYgDCS1FRnbzlw'),
            ]
        );

        $this->assertSame(
            'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
            WebPush::b64u($body)
        );
    }

    public function test_an_encrypted_payload_round_trips_for_the_subscriber_including_unicode(): void
    {
        $json = json_encode(['title' => 'علی', 'body' => 'سلام 👋 hello'], JSON_UNESCAPED_UNICODE);
        $body = WebPush::encrypt($json, WebPush::b64uDecode($this->browser['public']), WebPush::b64uDecode($this->browser['auth']));

        $this->assertSame($json, WebPush::decrypt(
            $body, WebPush::b64uDecode($this->browser['private']), WebPush::b64uDecode($this->browser['public']), WebPush::b64uDecode($this->browser['auth'])
        ));
        // and it is genuinely opaque
        $this->assertStringNotContainsString('hello', $body);
    }

    public function test_the_vapid_token_is_a_valid_es256_jwt_for_the_push_services_origin(): void
    {
        $jwt = WebPush::vapidJwt('https://fcm.googleapis.com');
        [$h, $c, $sig] = explode('.', $jwt);

        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(WebPush::b64uDecode($h), true));
        $claims = json_decode(WebPush::b64uDecode($c), true);
        $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
        $this->assertSame('mailto:test@example.com', $claims['sub']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertLessThan(time() + 24 * 3600, $claims['exp']);

        // verify it the way the push service does: raw r||s -> DER, check against the PUBLIC key
        $raw = WebPush::b64uDecode($sig);
        $this->assertSame(64, strlen($raw));
        $der = $this->rawToDer($raw);
        $pub = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . WebPush::b64uDecode(config('services.webpush.public_key'));
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($pub), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $this->assertSame(1, openssl_verify("$h.$c", $der, $pem, OPENSSL_ALGO_SHA256));
    }

    private function rawToDer(string $raw): string
    {
        $int = function (string $n) {
            $n = ltrim($n, "\0");
            if ($n === '' || ord($n[0]) & 0x80) $n = "\0" . $n;
            return "\x02" . chr(strlen($n)) . $n;
        };
        $body = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
        return "\x30" . chr(strlen($body)) . $body;
    }

    public function test_subscribe_stores_the_device_and_moves_it_to_whoever_is_signed_in(): void
    {
        $a = User::factory()->create(['is_active' => true]);
        $b = User::factory()->create(['is_active' => true]);
        $payload = ['endpoint' => 'https://push.example.com/send/xyz', 'keys' => ['p256dh' => $this->browser['public'], 'auth' => $this->browser['auth']]];

        $this->actingAs($a)->postJson('/push/subscribe', $payload)->assertOk();
        $this->assertSame($a->id, PushSubscription::first()->user_id);

        $this->actingAs($b)->postJson('/push/subscribe', $payload)->assertOk();   // same browser, other login
        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($b->id, PushSubscription::first()->user_id);
    }

    public function test_subscribe_rejects_non_https_endpoints_and_requires_login(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $this->actingAs($u)->postJson('/push/subscribe', ['endpoint' => 'http://insecure.example/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertStatus(422);
        auth()->logout();
        $this->postJson('/push/subscribe', ['endpoint' => 'https://a.b/c', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertUnauthorized();
    }

    public function test_unsubscribe_only_removes_your_own_device(): void
    {
        $a = User::factory()->create(['is_active' => true]);
        $b = User::factory()->create(['is_active' => true]);
        $this->subscribe($a, 'https://push.example.com/send/a');

        $this->actingAs($b)->postJson('/push/unsubscribe', ['endpoint' => 'https://push.example.com/send/a'])->assertOk();
        $this->assertSame(1, PushSubscription::count());

        $this->actingAs($a)->postJson('/push/unsubscribe', ['endpoint' => 'https://push.example.com/send/a'])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_a_push_is_signed_encrypted_and_addressed_to_the_devices_endpoint(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $u = User::factory()->create(['is_active' => true]);
        $this->subscribe($u);

        WebPush::sendToUsers([$u->id], ['title' => 'Hi', 'body' => 'secret body', 'url' => '/chat?conv=3']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $req) {
            $data = $this->decryptSent($req);
            return $req->url() === 'https://push.example.com/send/abc'
                && str_starts_with($req->header('Authorization')[0], 'vapid t=')
                && str_contains($req->header('Authorization')[0], 'k=' . config('services.webpush.public_key'))
                && $req->header('Content-Encoding')[0] === 'aes128gcm'
                && $data['body'] === 'secret body' && $data['url'] === '/chat?conv=3'
                && !str_contains($req->body(), 'secret body');
        });
    }

    public function test_an_expired_subscription_is_forgotten(): void
    {
        Http::fake(['*' => Http::response('gone', 410)]);
        $u = User::factory()->create(['is_active' => true]);
        $this->subscribe($u);

        WebPush::sendToUsers([$u->id], ['title' => 'Hi']);

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_a_chat_message_pushes_to_the_other_members_but_not_the_sender(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $me    = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $conv  = Conversation::create(['type' => 'direct', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $other->id]);
        $this->subscribe($me, 'https://push.example.com/send/me');
        $this->subscribe($other, 'https://push.example.com/send/other');

        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", ['content' => "line one\n\n  line two [img]/uploads/x.png[/img]"])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $req) use ($me, $conv) {
            $d = $this->decryptSent($req);
            return $req->url() === 'https://push.example.com/send/other'
                && $d['title'] === $me->name                      // direct chat: titled with the sender
                && $d['body'] === 'line one line two 📷 Photo'
                && $d['url'] === '/chat?conv=' . $conv->id
                && $d['tag'] === 'chat-' . $conv->id;
        });
    }

    public function test_group_pushes_carry_the_group_name_and_who_wrote_it(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $me    = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $conv  = Conversation::create(['type' => 'group', 'name' => 'Design team', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $other->id]);
        $this->subscribe($other);

        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", ['content' => 'standup now'])->assertOk();

        Http::assertSent(function (Request $req) use ($me) {
            $d = $this->decryptSent($req);
            return $d['title'] === 'Design team' && $d['body'] === $me->name . ': standup now';
        });
    }

    public function test_people_who_switched_message_alerts_off_get_no_push(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $me    = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true, 'notify_messages' => false]);
        $conv  = Conversation::create(['type' => 'direct', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $other->id]);
        $this->subscribe($other);

        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", ['content' => 'hello'])->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_mentioned_person_gets_one_mention_push_not_two(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $me    = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $conv  = Conversation::create(['type' => 'direct', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $other->id]);
        $this->subscribe($other);

        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", ['content' => '@x look', 'mentions' => [$other->id]])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $req) => str_contains($this->decryptSent($req)['body'], 'mentioned you') && $this->decryptSent($req)['url'] === '/chat?conv=' . $conv->id);
    }

    public function test_task_notifications_push_to_the_assignee_with_a_link_to_the_task_and_respect_the_switch(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $actor = User::factory()->create(['is_active' => true]);
        $on    = User::factory()->create(['is_active' => true]);
        $off   = User::factory()->create(['is_active' => true, 'notify_tasks' => false]);
        $task  = Task::create(['title' => 'Ship the logo', 'created_by' => $actor->id, 'priority' => 'low', 'status' => 'new']);
        $this->subscribe($on, 'https://push.example.com/send/on');
        $this->subscribe($off, 'https://push.example.com/send/off');

        Notification::notify([$on->id, $off->id, $actor->id], $actor, 'assigned', $task, 'Huzaifa assigned you a task');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $req) use ($task) {
            $d = $this->decryptSent($req);
            return $req->url() === 'https://push.example.com/send/on'
                && $d['title'] === 'Ship the logo' && $d['body'] === 'Huzaifa assigned you a task'
                && $d['url'] === '/tasks/kanban?task=' . $task->id;
        });
    }

    public function test_the_detached_command_pipes_the_binary_body_and_quotes_everything(): void
    {
        $req = WebPush::buildRequest('https://push.example.com/send/$(touch pwned)', $this->browser['public'], $this->browser['auth'], '{"a":1}');
        $cmd = WebPush::detachedCommand($req);

        $this->assertStringStartsWith('{ printf %s ', $cmd);
        $this->assertStringEndsWith('} &', $cmd);
        $this->assertStringContainsString(escapeshellarg($req['url']), $cmd);
        $this->assertStringContainsString(escapeshellarg(base64_encode($req['body'])), $cmd);
        $this->assertStringContainsString('--data-binary @-', $cmd);
        $this->assertStringContainsString(escapeshellarg('Content-Encoding: aes128gcm'), $cmd);
    }

    public function test_the_service_worker_and_manifest_are_served_from_the_site_root(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
    }

    public function test_keys_command_prints_a_usable_pair(): void
    {
        [$pub, $priv] = WebPush::generateKeys();
        $this->assertSame(65, strlen(WebPush::b64uDecode($pub)));
        $this->assertSame(32, strlen(WebPush::b64uDecode($priv)));
        $this->artisan('webpush:keys')->expectsOutputToContain('WEBPUSH_PUBLIC_KEY=')->assertSuccessful();
    }
}
