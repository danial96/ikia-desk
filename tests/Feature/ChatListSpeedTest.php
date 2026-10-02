<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The conversation list runs on every poll of every open tab, so it was rewritten for speed
 * (unread counts without the per-row COALESCE join, throttled last_seen_at write). These pin
 * the behaviour so the optimisation can't silently change what users see.
 */
class ChatListSpeedTest extends TestCase
{
    use RefreshDatabase;

    private function convWith(User $me, User $other): Conversation
    {
        $conv = Conversation::create(['type' => 'direct', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $other->id]);
        return $conv;
    }

    private function say(Conversation $c, User $by, string $text, $at = null): Message
    {
        $m = Message::create(['conversation_id' => $c->id, 'user_id' => $by->id, 'content' => $text]);
        if ($at) \DB::table('messages')->where('id', $m->id)->update(['created_at' => $at, 'updated_at' => $at]);
        return $m;
    }

    private function unreadFor(User $me, Conversation $c): int
    {
        $row = collect($this->actingAs($me)->getJson('/api/chat/convs')->assertOk()->json('convs'))->firstWhere('id', $c->id);
        return $row['unread'];
    }

    public function test_unread_counts_others_messages_since_last_read_and_clears_when_opened(): void
    {
        $me = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $c = $this->convWith($me, $other);

        $this->say($c, $other, 'one'); $this->say($c, $other, 'two'); $this->say($c, $other, 'three');
        $this->say($c, $me, 'my own reply');                       // never counts as unread for me
        $this->assertSame(3, $this->unreadFor($me, $c));           // never opened: everything from them

        $this->actingAs($me)->getJson("/api/chat/convs/{$c->id}/msgs")->assertOk();   // opening marks read
        $this->assertSame(0, $this->unreadFor($me, $c));

        $this->say($c, $other, 'four', now()->addSeconds(5));
        $this->assertSame(1, $this->unreadFor($me, $c));
    }

    public function test_deleted_messages_are_not_counted_and_the_preview_is_the_latest_live_one(): void
    {
        $me = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $c = $this->convWith($me, $other);

        $this->say($c, $other, 'keep me');
        $gone = $this->say($c, $other, 'delete me');
        $gone->delete();

        $row = collect($this->actingAs($me)->getJson('/api/chat/convs')->json('convs'))->firstWhere('id', $c->id);
        $this->assertSame(1, $row['unread']);
        $this->assertSame('keep me', $row['lastMsg']['text']);
    }

    public function test_a_conversation_that_is_fully_read_reports_zero(): void
    {
        $me = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $c = $this->convWith($me, $other);

        $this->say($c, $other, 'old', now()->subHour());
        \DB::table('conversation_members')->where('conversation_id', $c->id)->where('user_id', $me->id)->update(['last_read_at' => now()]);

        $this->assertSame(0, $this->unreadFor($me, $c));
    }

    public function test_last_seen_is_only_written_when_the_stored_value_is_stale(): void
    {
        $fresh = now()->subSeconds(5)->startOfSecond();
        $me = User::factory()->create(['is_active' => true, 'last_seen_at' => $fresh]);

        $this->actingAs($me)->getJson('/api/chat/convs')->assertOk();
        $this->assertEquals($fresh->timestamp, $me->fresh()->last_seen_at->timestamp, 'a 5s-old value must not be rewritten');

        $me->forceFill(['last_seen_at' => now()->subMinute()])->save();
        $this->actingAs($me->fresh())->getJson('/api/chat/convs')->assertOk();
        $this->assertEqualsWithDelta(now()->timestamp, $me->fresh()->last_seen_at->timestamp, 3);

        $never = User::factory()->create(['is_active' => true, 'last_seen_at' => null]);
        $this->actingAs($never)->getJson('/api/chat/convs')->assertOk();
        $this->assertNotNull($never->fresh()->last_seen_at);
    }
}
