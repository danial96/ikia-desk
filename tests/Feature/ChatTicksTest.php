<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTicksTest extends TestCase
{
    use RefreshDatabase;

    public function test_msgs_endpoint_reports_the_other_members_last_seen_and_last_read_timestamps(): void
    {
        $me    = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true, 'last_seen_at' => now()->subMinute()]);
        $conv  = Conversation::create(['type' => 'direct', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $other->id]);

        // The other member hasn't opened this conversation yet — no last_read_at row value.
        $resp = $this->actingAs($me)->getJson("/api/chat/convs/{$conv->id}/msgs")->assertOk();
        $resp->assertJsonPath('otherLastReadTs', null);
        $this->assertNotNull($resp->json('otherLastSeenTs'));
        $this->assertEqualsWithDelta(now()->subMinute()->timestamp, $resp->json('otherLastSeenTs'), 2);

        // Now they open it (this endpoint call, as themselves, bumps their own last_read_at).
        $this->actingAs($other)->getJson("/api/chat/convs/{$conv->id}/msgs")->assertOk();

        $resp2 = $this->actingAs($me)->getJson("/api/chat/convs/{$conv->id}/msgs")->assertOk();
        $this->assertNotNull($resp2->json('otherLastReadTs'));
    }
}
