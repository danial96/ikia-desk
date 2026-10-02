<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LongMessageTest extends TestCase
{
    use RefreshDatabase;

    private function conv(): array
    {
        $me    = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $conv  = Conversation::create(['type' => 'direct', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $other->id]);
        return [$me, $other, $conv];
    }

    public function test_the_limit_is_15000_characters(): void
    {
        $this->assertSame(15000, Message::MAX_CHARS);
    }

    public function test_a_long_message_up_to_the_limit_is_sent_and_stored_in_full(): void
    {
        [$me, , $conv] = $this->conv();
        $text = str_repeat('a', Message::MAX_CHARS);

        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", ['content' => $text])->assertOk();

        $this->assertSame($text, Message::latest('id')->first()->content);
    }

    public function test_the_worst_case_of_all_emoji_still_fits_the_column(): void
    {
        [$me, , $conv] = $this->conv();
        $text = str_repeat('😀', Message::MAX_CHARS);        // 4 bytes each -> 60,000 bytes, under TEXT's 65,535

        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", ['content' => $text])->assertOk();

        $this->assertSame(Message::MAX_CHARS, mb_strlen(Message::latest('id')->first()->content));
    }

    public function test_over_the_limit_is_a_422_json_error_not_a_silent_redirect(): void
    {
        [$me, , $conv] = $this->conv();
        $over = str_repeat('a', Message::MAX_CHARS + 1);

        // exactly how the browser asks for it now (Accept: application/json)
        $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", ['content' => $over])
            ->assertStatus(422)->assertJsonValidationErrors('content');
        $this->assertSame(0, Message::count());
    }

    public function test_the_edit_endpoint_has_the_same_limit(): void
    {
        [$me, , $conv] = $this->conv();
        $msg = Message::create(['conversation_id' => $conv->id, 'user_id' => $me->id, 'content' => 'short']);

        $this->actingAs($me)->patchJson("/api/chat/msgs/{$msg->id}", ['content' => str_repeat('b', Message::MAX_CHARS)])->assertOk();
        $this->actingAs($me)->patchJson("/api/chat/msgs/{$msg->id}", ['content' => str_repeat('b', Message::MAX_CHARS + 1)])->assertStatus(422);
    }

    public function test_task_comments_accept_the_same_length_and_reject_beyond_it(): void
    {
        $me   = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $task = Task::create(['title' => 'T', 'created_by' => $me->id, 'priority' => 'low', 'status' => 'new']);

        $this->actingAs($me)->postJson(route('api.local.comment', $task->id), ['content' => str_repeat('c', Message::MAX_CHARS)])
            ->assertOk()->assertJsonPath('ok', true);
        $this->actingAs($me)->postJson(route('api.local.comment', $task->id), ['content' => str_repeat('c', Message::MAX_CHARS + 1)])
            ->assertStatus(422)->assertJsonMissingPath('comment');
    }

    public function test_the_conversation_list_only_carries_a_short_preview_of_a_huge_last_message(): void
    {
        [$me, $other, $conv] = $this->conv();
        Message::create(['conversation_id' => $conv->id, 'user_id' => $other->id, 'content' => str_repeat('z', Message::MAX_CHARS)]);

        $row = collect($this->actingAs($me)->getJson('/api/chat/convs')->json('convs'))->firstWhere('id', $conv->id);

        $this->assertSame(600, mb_strlen($row['lastMsg']['text']));      // not 15,000 characters on every poll
        $this->assertSame(1, $row['unread']);
        // and opening the conversation still returns the whole message
        $full = $this->actingAs($me)->getJson("/api/chat/convs/{$conv->id}/msgs")->json('messages.0.text');
        $this->assertSame(Message::MAX_CHARS, mb_strlen($full));
    }
}
