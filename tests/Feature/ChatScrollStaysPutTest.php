<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatScrollStaysPutTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $url): string
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        return $this->actingAs($u)->get($url)->assertOk()->getContent();
    }

    public function test_a_redraw_or_a_late_image_never_pulls_a_reader_who_scrolled_up_back_down(): void
    {
        $html = $this->page('/chat');

        // shared helper: pins to the newest message while images load, until the reader scrolls themselves
        $this->assertStringContainsString('window.chatPin = function (el)', $html);
        $this->assertStringContainsString("el.addEventListener('wheel', moved, { passive: true });", $html);
        $this->assertStringContainsString('return function () { if (!el._userMoved) el.scrollTop = el.scrollHeight + 9999; };', $html);
        // the Messenger page uses it, and keeps the reader's place when it has to redraw
        $this->assertStringContainsString('const goBottom = doScroll ? window.chatPin(el) : null;', $html);
        $this->assertStringContainsString('if (!doScroll) el.scrollTop = el.scrollHeight - prevDist;', $html);
        $this->assertStringContainsString('cpRenderMsgs(_initMsgs, !_moved, _moved);', $html);
        // so does the popup chat
        $this->assertStringContainsString('const goBottomChat = window.chatPin(el);', $html);
    }

    public function test_only_one_scroll_to_newest_arrow_is_shown_and_none_for_a_covered_list(): void
    {
        $html = $this->page('/chat');

        $this->assertStringContainsString('let shown = false;', $html);
        $this->assertStringContainsString('covered = !!t && !el.contains(t);', $html);
        $this->assertStringContainsString('const far = visible && !covered && !shown &&', $html);
    }
}
