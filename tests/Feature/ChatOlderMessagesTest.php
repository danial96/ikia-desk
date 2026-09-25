<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatOlderMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scrolling_up_pages_through_the_whole_history_even_with_same_second_messages(): void
    {
        $a = User::factory()->create(['is_active' => true]);
        $b = User::factory()->create(['is_active' => true]);
        $c = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        $c->members()->attach([$a->id, $b->id]);

        // 130 messages, many sharing the same second (like a bulk import)
        $base = now()->subDays(3)->startOfSecond();
        for ($i = 1; $i <= 130; $i++) {
            $m = Message::create(['conversation_id' => $c->id, 'user_id' => $i % 2 ? $a->id : $b->id, 'content' => "m$i"]);
            $m->forceFill(['created_at' => $base->copy()->addSeconds(intdiv($i, 4)), 'updated_at' => $base])->save();
        }

        $first = $this->actingAs($a)->getJson("/api/chat/convs/{$c->id}/msgs")->assertOk()->json();
        $seen  = collect($first['messages'])->pluck('id')->all();
        $this->assertCount(50, $seen);
        $this->assertTrue($first['hasMore']);

        $ts = min(array_column($first['messages'], 'createdTs'));
        $id = min(array_column(array_filter($first['messages'], fn($m) => $m['createdTs'] === $ts), 'id'));
        for ($guard = 0; $guard < 6; $guard++) {
            $page = $this->actingAs($a)->getJson("/api/chat/convs/{$c->id}/msgs?before_ts=$ts&before_id=$id")->assertOk()->json();
            $seen = array_merge($seen, array_column($page['messages'], 'id'));
            if (!$page['messages']) break;
            $ts = min(array_column($page['messages'], 'createdTs'));
            $id = min(array_column(array_filter($page['messages'], fn($m) => $m['createdTs'] === $ts), 'id'));
            if (!$page['hasMore']) break;
        }

        $this->assertCount(130, array_unique($seen), 'every message must be reachable exactly once');
    }
}
