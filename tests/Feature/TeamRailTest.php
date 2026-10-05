<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeamRailTest extends TestCase
{
    use RefreshDatabase;

    private function rail(User $me)
    {
        $provider = app()->getProvider(AppServiceProvider::class);
        $m = new \ReflectionMethod($provider, 'teamRail');
        $m->setAccessible(true);
        return $m->invoke($provider, $me->id);
    }

    private function direct(User $a, User $b, string $lastAt, ?string $text = 'hi'): Conversation
    {
        $c = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        $c->members()->attach([$a->id, $b->id]);
        if ($text !== null) {
            $m = Message::create(['conversation_id' => $c->id, 'user_id' => $b->id, 'content' => $text]);
            DB::table('messages')->where('id', $m->id)->update(['created_at' => $lastAt]);
        }
        return $c;
    }

    public function test_people_you_talked_to_most_recently_come_first_then_everyone_else_alphabetically(): void
    {
        $me = User::factory()->create(['is_active' => true, 'name' => 'Me']);
        $bob = User::factory()->create(['is_active' => true, 'name' => 'Bob']);
        $cat = User::factory()->create(['is_active' => true, 'name' => 'Cat']);
        $dan = User::factory()->create(['is_active' => true, 'name' => 'Dan']);
        $amy = User::factory()->create(['is_active' => true, 'name' => 'Amy']);
        User::factory()->create(['is_active' => false, 'name' => 'Gone']);

        $this->direct($me, $bob, '2026-10-01 09:00:00');
        $this->direct($me, $cat, '2026-10-03 09:00:00');               // newest -> first
        $this->direct($bob, $dan, '2026-10-04 09:00:00');              // not my chat: must not count

        $names = $this->rail($me)->pluck('name')->all();

        $this->assertSame(['Cat', 'Bob', 'Amy', 'Dan'], $names);       // chatted: Cat, Bob; the rest A-Z; inactive + me excluded
    }

    public function test_deleted_messages_and_group_chats_do_not_affect_the_order(): void
    {
        $me = User::factory()->create(['is_active' => true, 'name' => 'Me']);
        $bob = User::factory()->create(['is_active' => true, 'name' => 'Bob']);
        $cat = User::factory()->create(['is_active' => true, 'name' => 'Cat']);

        $this->direct($me, $bob, '2026-10-01 09:00:00');
        $c = $this->direct($me, $cat, '2026-10-02 09:00:00');
        // Cat's chat has an even newer message, but it was deleted -> Cat keeps the 10-02 time, still ahead of Bob
        $m = Message::create(['conversation_id' => $c->id, 'user_id' => $cat->id, 'content' => 'oops']);
        DB::table('messages')->where('id', $m->id)->update(['created_at' => '2026-10-09 09:00:00', 'deleted_at' => '2026-10-09 09:01:00']);
        // a group chat with Bob that is very recent must not make Bob jump ahead
        $g = Conversation::create(['type' => 'group', 'name' => 'G', 'created_by' => $me->id]);
        $g->members()->attach([$me->id, $bob->id, $cat->id]);
        $gm = Message::create(['conversation_id' => $g->id, 'user_id' => $bob->id, 'content' => 'group']);
        DB::table('messages')->where('id', $gm->id)->update(['created_at' => '2026-10-10 09:00:00']);

        $this->assertSame(['Cat', 'Bob'], $this->rail($me)->pluck('name')->all());
    }

    public function test_the_rail_never_scans_the_whole_messages_table(): void
    {
        $me = User::factory()->create(['is_active' => true]);
        $bob = User::factory()->create(['is_active' => true]);
        $this->direct($me, $bob, '2026-10-01 09:00:00');

        $sql = [];
        DB::listen(function ($q) use (&$sql) { $sql[] = $q->sql; });
        $this->rail($me);

        foreach ($sql as $s) {
            if (str_contains($s, 'from `messages`') || str_contains($s, 'from "messages"')) {
                // every touch of messages is restricted to specific conversations or specific ids
                $this->assertMatchesRegularExpression('/(conversation_id|"id"|`id`)[^a-z]*in \(/i', $s);
            }
        }
        $this->assertLessThanOrEqual(5, count($sql));
    }

    public function test_the_rail_shows_online_dots_from_last_seen(): void
    {
        $me = User::factory()->create(['is_active' => true]);
        $on = User::factory()->create(['is_active' => true, 'last_seen_at' => now()->subMinute()]);
        $off = User::factory()->create(['is_active' => true, 'last_seen_at' => now()->subHour()]);

        $rail = $this->rail($me)->keyBy('id');
        $this->assertTrue((bool) $rail[$on->id]->is_online);
        $this->assertFalse((bool) $rail[$off->id]->is_online);
    }
}
