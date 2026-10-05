<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceNoteUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_pages_ship_the_voice_note_player_with_seek_speed_and_a_live_recording_meter(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($user)->get('/chat')->assertOk()->getContent();

        $this->assertStringContainsString('window.voiceSeek = function', $html);       // click the waveform to jump
        $this->assertStringContainsString('window.voiceSpeed = function', $html);      // 1x / 1.5x / 2x, remembered
        $this->assertStringContainsString("class=\"vn-speed\"", $html);
        $this->assertStringContainsString('function vnMeterStart', $html);              // live level bars while recording
        $this->assertStringContainsString("localStorage.setItem('vn_speed'", $html);
    }

    public function test_forwarding_a_message_sends_the_csrf_token_like_every_other_chat_request(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($user)->get('/chat')->assertOk()->getContent();

        $start = strpos($html, 'function forward(rawText');
        $this->assertNotFalse($start);
        $block = substr($html, $start, 2500);
        $this->assertStringContainsString("'X-CSRF-TOKEN': csrf", $block);        // without it the server answers 419 and the forward just "fails"
    }
}
