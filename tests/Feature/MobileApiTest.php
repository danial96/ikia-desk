<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Realtime;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true, 'password' => Hash::make('secret-pass')], $attrs));
    }

    /** A signed-in phone: returns the plain bearer token. */
    private function phone(User $u): string
    {
        return ApiToken::issue($u, 'Test phone', 'android', '1.0.0')[1];
    }

    private function task(User $creator, array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => 'Card', 'created_by' => $creator->id, 'priority' => 'low', 'status' => 'new']);
    }

    // ───────────────────────── sign in ─────────────────────────

    public function test_login_returns_a_token_and_the_person(): void
    {
        $u = $this->user(['role' => 'admin']);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => $u->email, 'password' => 'secret-pass', 'device_name' => 'Pixel 8', 'platform' => 'android', 'app_version' => '1.0.0',
        ])->assertOk()
          ->assertJsonPath('user.id', $u->id)
          ->assertJsonPath('user.can.is_admin', true)
          ->assertJsonPath('user.can.create_tasks', true);

        $token = $res->json('token');
        $this->assertSame(64, strlen($token));
        // only the hash is stored, never the token itself
        $this->assertDatabaseHas('api_tokens', ['user_id' => $u->id, 'token_hash' => ApiToken::hashFor($token), 'device_name' => 'Pixel 8', 'platform' => 'android']);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $token]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.email', $u->email);
    }

    public function test_the_api_sets_no_cookie_and_starts_no_session(): void
    {
        $u = $this->user();

        $res = $this->withToken($this->phone($u))->getJson('/api/v1/auth/me')->assertOk();

        $this->assertEmpty($res->headers->getCookies());
    }

    public function test_wrong_password_unknown_email_and_deactivated_accounts_are_refused(): void
    {
        $u = $this->user();
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'nope'])->assertStatus(422)->assertJsonPath('error', 'invalid_credentials');
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'secret-pass'])->assertStatus(422);

        $gone = $this->user(['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $gone->email, 'password' => 'secret-pass'])->assertStatus(403)->assertJsonPath('error', 'deactivated');
        $this->assertSame(0, ApiToken::count());
    }

    public function test_five_wrong_passwords_lock_the_attempts_for_a_minute(): void
    {
        $u = $this->user();
        RateLimiter::clear('api-login|' . \Illuminate\Support\Str::transliterate(strtolower($u->email) . '|127.0.0.1'));

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'bad'])->assertStatus(422);
        }
        // even the right password waits now
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'secret-pass'])->assertStatus(429)->assertJsonPath('error', 'too_many_attempts');
    }

    public function test_requests_without_a_good_token_are_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401)->assertJsonPath('error', 'unauthenticated');
        $this->withToken('not-a-real-token')->getJson('/api/v1/tasks')->assertStatus(401);
    }

    public function test_a_website_session_does_not_sign_the_api_in(): void
    {
        $u = $this->user();

        $this->actingAs($u)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_a_deactivated_person_is_locked_out_even_with_a_valid_token(): void
    {
        $u = $this->user();
        $t = $this->phone($u);
        $this->withToken($t)->getJson('/api/v1/auth/me')->assertOk();

        $u->update(['is_active' => false]);

        $this->withToken($t)->getJson('/api/v1/auth/me')->assertStatus(401)->assertJsonPath('error', 'deactivated');
    }

    public function test_logout_revokes_that_phone_only_and_forgets_its_push_registration(): void
    {
        $u = $this->user();
        $a = $this->phone($u);
        $b = $this->phone($u);
        $this->withToken($a)->postJson('/api/v1/devices', ['push_token' => 'fcm-a', 'platform' => 'android'])->assertOk();

        $this->withToken($a)->postJson('/api/v1/auth/logout')->assertOk();

        $this->withToken($a)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->withToken($b)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_a_new_password_signs_every_phone_out(): void
    {
        $u = $this->user();
        $t = $this->phone($u);

        $u->update(['password' => 'brand-new-pass-1']);

        $this->withToken($t)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->assertSame(0, ApiToken::where('user_id', $u->id)->count());
    }

    public function test_changing_other_details_keeps_phones_signed_in(): void
    {
        $u = $this->user();
        $t = $this->phone($u);

        $u->update(['position' => 'Designer']);

        $this->withToken($t)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.position', 'Designer');
    }

    public function test_forgot_password_answers_the_same_for_known_and_unknown_emails(): void
    {
        $u = $this->user();

        $known   = $this->postJson('/api/v1/auth/forgot-password', ['email' => $u->email])->assertOk()->json();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com'])->assertOk()->json();

        $this->assertSame($known, $unknown);
    }

    // ───────────────────────── app config / versions ─────────────────────────

    public function test_app_config_is_public(): void
    {
        config(['desk.mobile.min_version' => '1.2.0', 'desk.mobile.store.android' => 'https://play.example/ikia']);

        $this->getJson('/api/v1/app/config')->assertOk()
            ->assertJsonPath('min_version', '1.2.0')
            ->assertJsonPath('store.android', 'https://play.example/ikia');
    }

    public function test_builds_older_than_the_minimum_are_told_to_update(): void
    {
        config(['desk.mobile.min_version' => '1.2.0']);
        $t = $this->phone($this->user());

        $this->withToken($t)->withHeader('X-App-Version', '1.1.9')->getJson('/api/v1/auth/me')
            ->assertStatus(426)->assertJsonPath('error', 'update_required')->assertJsonPath('min_version', '1.2.0');
        $this->withToken($t)->withHeader('X-App-Version', '1.2.0')->getJson('/api/v1/auth/me')->assertOk();
        $this->withToken($t)->withHeader('X-App-Version', '1.10.0')->getJson('/api/v1/auth/me')->assertOk();   // 1.10 is newer than 1.2
        $this->withToken($t)->getJson('/api/v1/auth/me')->assertOk();                                           // no header: never blocked
    }

    // ───────────────────────── push registration ─────────────────────────

    public function test_a_phone_registers_its_push_token_and_it_moves_to_whoever_signs_in_next(): void
    {
        $a = $this->user();
        $b = $this->user();

        $this->withToken($this->phone($a))->postJson('/api/v1/devices', ['push_token' => 'fcm-xyz', 'platform' => 'ios', 'app_version' => '1.0.0'])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['user_id' => $a->id, 'platform' => 'ios']);

        $this->withToken($this->phone($b))->postJson('/api/v1/devices', ['push_token' => 'fcm-xyz', 'platform' => 'ios'])->assertOk();
        $this->assertSame(1, DeviceToken::count());
        $this->assertDatabaseHas('device_tokens', ['user_id' => $b->id]);
    }

    public function test_you_can_only_unregister_your_own_device(): void
    {
        $a = $this->user();
        $b = $this->user();
        $this->withToken($this->phone($a))->postJson('/api/v1/devices', ['push_token' => 'fcm-a', 'platform' => 'android'])->assertOk();

        $this->withToken($this->phone($b))->deleteJson('/api/v1/devices', ['push_token' => 'fcm-a'])->assertOk();
        $this->assertSame(1, DeviceToken::count());

        $this->withToken($this->phone($a))->deleteJson('/api/v1/devices', ['push_token' => 'fcm-a'])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    // ───────────────────────── realtime ─────────────────────────

    public function test_realtime_auth_only_signs_your_own_channel(): void
    {
        config(['services.pusher' => ['app_id' => '1', 'key' => 'k', 'secret' => 's', 'cluster' => 'eu', 'channel_prefix' => null]]);
        $u = $this->user();
        $t = $this->phone($u);

        $this->withToken($t)->postJson('/api/v1/realtime/auth', ['socket_id' => '123.456', 'channel_name' => Realtime::userChannel($u->id)])
            ->assertOk()->assertJsonStructure(['auth']);
        $this->withToken($t)->postJson('/api/v1/realtime/auth', ['socket_id' => '123.456', 'channel_name' => Realtime::userChannel($u->id + 1)])
            ->assertForbidden();
    }

    // ───────────────────────── pick-lists ─────────────────────────

    public function test_users_and_projects_lists(): void
    {
        $me = $this->user(['name' => 'Aaa Me']);
        $this->user(['name' => 'Zed Gone', 'is_active' => false]);
        Project::create(['name' => 'Website', 'created_by' => $me->id]);
        $t = $this->phone($me);

        $names = collect($this->withToken($t)->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Aaa Me'));
        $this->assertFalse($names->contains('Zed Gone'));
        $this->assertSame('Website', $this->withToken($t)->getJson('/api/v1/projects')->assertOk()->json('data.0.name'));
    }

    // ───────────────────────── tasks ─────────────────────────

    public function test_the_task_list_shows_only_what_the_person_may_see(): void
    {
        $me     = $this->user();
        $other  = $this->user();
        $mine   = $this->task($me, ['title' => 'Mine']);
        $given  = $this->task($other, ['title' => 'Given to me', 'assigned_to' => $me->id]);
        $this->task($other, ['title' => 'Not mine']);

        $titles = collect($this->withToken($this->phone($me))->getJson('/api/v1/tasks')->assertOk()->json('data'))->pluck('title')->all();

        $this->assertEqualsCanonicalizing(['Mine', 'Given to me'], $titles);
    }

    public function test_view_all_tasks_permission_sees_everything(): void
    {
        $boss  = $this->user(['permissions' => ['view_all_tasks' => true]]);
        $other = $this->user();
        $this->task($other, ['title' => 'A']);
        $this->task($other, ['title' => 'B']);

        $this->withToken($this->phone($boss))->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_the_task_list_filters_and_pages(): void
    {
        $u = $this->user(['role' => 'super_admin']);
        $project = Project::create(['name' => 'P', 'created_by' => $u->id]);
        foreach (range(1, 5) as $i) $this->task($u, ['title' => "Open $i", 'status' => 'in_progress', 'project_id' => $project->id]);
        $this->task($u, ['title' => 'Finished', 'status' => 'completed']);
        $this->task($u, ['title' => 'Urgent one', 'status' => 'new', 'priority' => 'urgent']);
        $t = $this->phone($u);

        // "in progress" on the website also covers new / pending / reviewing
        $this->withToken($t)->getJson('/api/v1/tasks?status=in_progress')->assertJsonPath('meta.total', 6);
        $this->withToken($t)->getJson('/api/v1/tasks?status=completed')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Finished');
        $this->withToken($t)->getJson('/api/v1/tasks?priority=urgent')->assertJsonPath('data.0.title', 'Urgent one');
        $this->withToken($t)->getJson('/api/v1/tasks?project_id=' . $project->id)->assertJsonPath('meta.total', 5);
        $this->withToken($t)->getJson('/api/v1/tasks?search=Finished')->assertJsonPath('meta.total', 1);

        $page = $this->withToken($t)->getJson('/api/v1/tasks?per_page=4')->assertOk();
        $this->assertCount(4, $page->json('data'));
        $page->assertJsonPath('meta.has_more', true);
        $this->assertCount(3, $this->withToken($t)->getJson('/api/v1/tasks?per_page=4&page=2')->json('data'));
    }

    public function test_a_task_card_carries_what_the_list_screen_needs(): void
    {
        $u = $this->user();
        $this->task($u, ['title' => 'Late', 'status' => 'in_progress', 'deadline' => now()->subDay()]);

        $card = $this->withToken($this->phone($u))->getJson('/api/v1/tasks')->json('data.0');

        $this->assertTrue($card['is_overdue']);
        $this->assertSame('Late', $card['title']);
        $this->assertSame($u->id, $card['creator']['id']);
        $this->assertArrayHasKey('comments_count', $card);
        $this->assertNotNull($card['deadline']);
    }

    public function test_reading_the_board_does_not_change_the_layout_chosen_on_the_website(): void
    {
        $u = $this->user(['task_view' => 'list']);
        $this->task($u, ['title' => 'On the board', 'status' => 'in_progress']);

        $res = $this->withToken($this->phone($u))->getJson('/api/v1/tasks/board?status=in_progress')->assertOk();

        $this->assertSame('On the board', $res->json('columns.no_deadline.0.title'));
        $this->assertSame('list', $u->fresh()->task_view);
    }

    public function test_creating_and_editing_a_task_follow_the_website_rules(): void
    {
        $admin  = $this->user(['role' => 'admin']);
        $worker = $this->user();   // no create_tasks permission

        $this->withToken($this->phone($worker))->postJson('/api/v1/tasks', ['title' => 'Nope', 'priority' => 'low'])->assertForbidden();

        $t = $this->phone($admin);
        $id = $this->withToken($t)->postJson('/api/v1/tasks', ['title' => 'Ship it', 'priority' => 'high', 'assigned_to' => $worker->id])
            ->assertOk()->assertJsonPath('success', true)->json('task.id');
        $this->assertDatabaseHas('notifications', ['user_id' => $worker->id, 'type' => 'task_assigned', 'task_id' => $id]);

        // the assignee changes the status; a stranger cannot
        $this->withToken($this->phone($worker))->patchJson("/api/v1/tasks/$id/field", ['field' => 'status', 'value' => 'in_progress'])->assertOk()->assertJsonPath('status', 'in_progress');
        $stranger = $this->user();
        $this->withToken($this->phone($stranger))->patchJson("/api/v1/tasks/$id/field", ['field' => 'status', 'value' => 'completed'])->assertForbidden();

        $this->withToken($this->phone($worker))->patchJson("/api/v1/tasks/$id/move", ['status' => 'reviewing'])->assertOk()->assertJsonPath('success', true);
        $this->assertSame('reviewing', Task::find($id)->status);
    }

    public function test_deleting_restoring_and_the_trash_list(): void
    {
        $owner    = $this->user();
        $stranger = $this->user();
        $task     = $this->task($owner, ['title' => 'Bin me']);
        $o = $this->phone($owner);

        $this->withToken($this->phone($stranger))->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();
        $this->withToken($o)->deleteJson("/api/v1/tasks/{$task->id}")->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        $this->withToken($o)->getJson('/api/v1/tasks')->assertJsonPath('meta.total', 0);
        $this->withToken($o)->getJson('/api/v1/tasks/trash')->assertOk()
            ->assertJsonPath('data.0.title', 'Bin me')->assertJsonPath('data.0.deleted_by', $owner->name);
        $this->withToken($this->phone($stranger))->getJson('/api/v1/tasks/trash')->assertJsonPath('meta.total', 0);

        $this->withToken($this->phone($stranger))->postJson("/api/v1/tasks/{$task->id}/restore")->assertForbidden();
        $this->withToken($o)->postJson("/api/v1/tasks/{$task->id}/restore")->assertOk();
        $this->assertNull(Task::find($task->id)->deleted_at);

        $this->withToken($o)->deleteJson("/api/v1/tasks/{$task->id}")->assertOk();
        $this->withToken($o)->deleteJson("/api/v1/tasks/{$task->id}/force")->assertOk();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    // ───────────────────────── handlers shared with the website ─────────────────────────

    public function test_task_detail_comments_and_checklist_work_with_a_token(): void
    {
        $u    = $this->user();
        $task = $this->task($u, ['title' => 'Detail']);
        $t    = $this->phone($u);

        $this->withToken($t)->postJson("/api/v1/local-task/{$task->id}/comment", ['content' => 'first!'])->assertOk();
        $this->withToken($t)->postJson("/api/v1/local-task/{$task->id}/checklist", ['text' => 'step one'])->assertOk();

        $detail = $this->withToken($t)->getJson("/api/v1/local-task/{$task->id}")->assertOk();
        $detail->assertJsonPath('task.title', 'Detail');
        $this->assertSame('first!', collect($detail->json('feed'))->firstWhere('type', 'comment')['text']);
        $this->assertSame('step one', $detail->json('checklists.0.text'));

        // someone with no connection to the task is refused
        $this->withToken($this->phone($this->user()))->getJson("/api/v1/local-task/{$task->id}")->assertForbidden();
    }

    public function test_chat_works_with_a_token_and_notifies_the_other_person(): void
    {
        $me    = $this->user();
        $alice = $this->user();
        $conv  = Conversation::create(['type' => 'group', 'name' => 'Team', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $alice->id]);

        $sent = $this->withToken($this->phone($me))->postJson("/api/v1/chat/convs/{$conv->id}/send", ['content' => 'salam'])
            ->assertOk()->json('message');
        $this->assertSame('salam', $sent['text']);

        $list = $this->withToken($this->phone($alice))->getJson('/api/v1/chat/convs')->assertOk();
        $this->assertNotEmpty($list->json());

        $msgs = $this->withToken($this->phone($alice))->getJson("/api/v1/chat/convs/{$conv->id}/msgs")->assertOk()->json('messages');
        $this->assertSame('salam', $msgs[0]['text']);
        $this->assertFalse($msgs[0]['isMine']);

        // not a member: refused
        $this->withToken($this->phone($this->user()))->getJson("/api/v1/chat/convs/{$conv->id}/msgs")->assertForbidden();
    }

    public function test_a_file_can_be_uploaded_and_downloaded_with_a_token_but_not_without_one(): void
    {
        $u = $this->user();
        $t = $this->phone($u);
        $stored = null;
        try {
            $res = $this->withToken($t)->post('/api/v1/upload', ['file' => UploadedFile::fake()->createWithContent('Report 1.txt', 'hello phone')], ['Accept' => 'application/json'])
                ->assertOk()->assertJsonPath('name', 'Report 1.txt');

            // the url the server hands back is the website form ("/uploads/<id>/<name>"); the app asks for the same path under /api/v1
            $path = ltrim(parse_url($res->json('url'), PHP_URL_PATH), '/');            // uploads/up_xxx.txt/Report%201.txt
            $stored = Uploads::path(explode('/', $path)[1]);

            $this->withToken($t)->get('/api/v1/' . $path)->assertOk();
            $this->withoutToken()->get('/api/v1/' . $path, ['Accept' => 'application/json'])->assertStatus(401);
        } finally {
            if ($stored) @unlink($stored);
        }
    }

    public function test_the_profile_names_the_realtime_channel_only_when_realtime_is_on(): void
    {
        $u = $this->user();
        $t = $this->phone($u);

        $this->withToken($t)->getJson('/api/v1/auth/me')->assertJsonPath('user.realtime_channel', null);

        config(['services.pusher' => ['app_id' => '1', 'key' => 'k', 'secret' => 's', 'cluster' => 'eu', 'channel_prefix' => null]]);
        $this->withToken($t)->getJson('/api/v1/auth/me')->assertJsonPath('user.realtime_channel', Realtime::userChannel($u->id));
    }

    public function test_the_conversation_list_carries_a_timestamp_for_the_last_message(): void
    {
        $me    = $this->user();
        $alice = $this->user();
        $conv  = Conversation::create(['type' => 'group', 'name' => 'Team', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $alice->id]);
        $conv->messages()->create(['user_id' => $alice->id, 'content' => 'hello']);

        $row = collect($this->withToken($this->phone($me))->getJson('/api/v1/chat/convs')->json('convs'))->firstWhere('id', $conv->id);

        $this->assertSame('hello', $row['lastMsg']['text']);
        $this->assertEqualsWithDelta(time(), $row['lastMsg']['ts'], 5);
        $this->assertSame(1, $row['unread']);
    }

    public function test_a_voice_call_can_be_placed_answered_signalled_and_ended_with_tokens(): void
    {
        config(['services.pusher' => ['app_id' => '1', 'key' => 'k', 'secret' => 's', 'cluster' => 'eu', 'channel_prefix' => null]]);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('{}', 200)]);
        $caller = $this->user();
        $callee = $this->user();
        $c = $this->phone($caller);
        $b = $this->phone($callee);

        $this->withToken($c)->getJson('/api/v1/calls/ice')->assertOk()->assertJsonStructure(['iceServers']);

        $id = $this->withToken($c)->postJson('/api/v1/calls', ['callee_id' => $callee->id])
            ->assertOk()->assertJsonPath('call.status', 'ringing')->json('call.id');

        // the person called is busy-checked and can see and answer it; a stranger cannot
        $this->withToken($this->phone($this->user()))->getJson("/api/v1/calls/$id")->assertForbidden();
        $this->withToken($b)->getJson("/api/v1/calls/$id")->assertOk()->assertJsonPath('call.caller.id', $caller->id);
        $this->withToken($c)->postJson("/api/v1/calls/$id/accept")->assertForbidden();         // only the callee may answer
        $this->withToken($b)->postJson("/api/v1/calls/$id/accept")->assertOk()->assertJsonPath('call.status', 'active');

        $this->withToken($c)->postJson("/api/v1/calls/$id/signal", ['type' => 'offer', 'data' => ['type' => 'offer', 'sdp' => 'v=0']])->assertOk();
        $this->withToken($b)->postJson("/api/v1/calls/$id/ping")->assertOk()->assertJsonPath('status', 'active');

        $this->withToken($b)->postJson("/api/v1/calls/$id/end")->assertOk()->assertJsonPath('call.status', 'ended');
        $this->withToken($c)->postJson("/api/v1/calls/$id/signal", ['type' => 'ice', 'data' => ['list' => []]])->assertStatus(409);
        $this->assertDatabaseHas('messages', ['conversation_id' => \App\Models\Call::find($id)->conversation_id, 'user_id' => $caller->id]);
    }

    public function test_calls_are_off_when_realtime_is_not_set_up(): void
    {
        config(['services.pusher' => ['app_id' => null, 'key' => null, 'secret' => null, 'cluster' => null]]);

        $this->withToken($this->phone($this->user()))->getJson('/api/v1/calls/ice')->assertNotFound();
    }

    public function test_notifications_can_be_read_and_cleared_with_a_token(): void
    {
        $me = $this->user();
        $other = $this->user();
        $task = $this->task($other, ['assigned_to' => $me->id]);
        \App\Models\Notification::notify([$me->id], $other, 'task_assigned', $task, 'assigned you');
        $t = $this->phone($me);

        $this->withToken($t)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 1);
        $this->withToken($t)->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->withToken($t)->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_notification_preferences_can_be_saved(): void
    {
        $u = $this->user();

        $this->withToken($this->phone($u))->postJson('/api/v1/me/notifications', [
            'notify_messages' => true, 'notify_messages_sound' => false, 'notify_tasks' => true, 'notify_tasks_sound' => false,
        ])->assertOk();

        $this->assertFalse($u->fresh()->notify_messages_sound);
        $this->withToken($this->phone($u))->getJson('/api/v1/auth/me')->assertJsonPath('user.notifications.messages', true)->assertJsonPath('user.notifications.tasks_sound', false);
    }

    public function test_the_website_routes_are_unchanged_and_still_need_a_session(): void
    {
        $u = $this->user();
        $task = $this->task($u);

        $this->getJson('/api/notifications')->assertStatus(401);                       // no session, no bearer support on the website
        $this->withToken($this->phone($u))->getJson('/api/notifications')->assertStatus(401);
        $this->actingAs($u)->getJson("/api/local-task/{$task->id}")->assertOk();
    }
}
