<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_message_near_midnight_lands_on_a_different_calendar_day_for_a_viewer_further_east(): void
    {
        $karachi = User::factory()->create(['is_active' => true, 'time_zone' => 'Asia/Karachi']);
        $tokyo   = User::factory()->create(['is_active' => true, 'time_zone' => 'Asia/Tokyo']); // +4h ahead of Karachi
        $conv    = Conversation::create(['type' => 'direct', 'created_by' => $karachi->id]);
        $conv->members()->attach([$karachi->id, $tokyo->id]);

        // 11:30 pm Karachi time — already the next calendar day in Tokyo (+4h → 3:30 am).
        $msg = $conv->messages()->create(['user_id' => $karachi->id, 'content' => 'late night message']);
        \DB::table('messages')->where('id', $msg->id)->update(['created_at' => '2026-09-28 23:30:00']);

        $asKarachi = $this->actingAs($karachi)->getJson("/api/chat/convs/{$conv->id}/msgs")->json('messages.0');
        $asTokyo   = $this->actingAs($tokyo)->getJson("/api/chat/convs/{$conv->id}/msgs")->json('messages.0');

        $this->assertSame('2026-09-28', $asKarachi['date']);
        $this->assertSame('11:30 pm', $asKarachi['time']);

        $this->assertSame('2026-09-29', $asTokyo['date']);
        $this->assertSame('3:30 am', $asTokyo['time']);
    }
}
