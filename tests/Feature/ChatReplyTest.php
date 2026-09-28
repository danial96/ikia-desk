<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_replying_to_a_message_stores_a_real_link_instead_of_prefixing_the_text(): void
    {
        $me    = User::factory()->create(['is_active' => true]);
        $alice = User::factory()->create(['is_active' => true]);
        $conv  = Conversation::create(['type' => 'group', 'name' => 'Team', 'created_by' => $me->id]);
        $conv->members()->attach([$me->id, $alice->id]);

        $first = $this->actingAs($alice)->postJson("/api/chat/convs/{$conv->id}/send", [
            'content' => 'yeh kya hai?',
        ])->assertOk()->json('message.id');

        Notification::query()->delete();

        $reply = $this->actingAs($me)->postJson("/api/chat/convs/{$conv->id}/send", [
            'content'   => 'han thi lekn ab ni arahi hai urh gai',
            'parent_id' => $first,
        ])->assertOk()->json('message');

        // the reply's own content must stay exactly what was typed — no "> ..." text prefix baked in
        $this->assertSame('han thi lekn ab ni arahi hai urh gai', $reply['text']);
        $this->assertSame($first, $reply['parentId']);
        $this->assertSame('yeh kya hai?', $reply['parentPreview']['text']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $alice->id, 'actor_id' => $me->id, 'type' => 'mention',
        ]);

        $feed = $this->actingAs($alice)->getJson("/api/chat/convs/{$conv->id}/msgs")->json('messages');
        $fed  = collect($feed)->firstWhere('id', $reply['id']);
        $this->assertSame($first, $fed['parentId']);
        $this->assertSame('yeh kya hai?', $fed['parentPreview']['text']);
    }

    public function test_a_parent_id_from_another_conversation_is_ignored(): void
    {
        $me    = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $convA = Conversation::create(['type' => 'group', 'name' => 'A', 'created_by' => $me->id]);
        $convA->members()->attach([$me->id]);
        $convB = Conversation::create(['type' => 'group', 'name' => 'B', 'created_by' => $other->id]);
        $convB->members()->attach([$other->id]);

        $foreignMsgId = $this->actingAs($other)->postJson("/api/chat/convs/{$convB->id}/send", [
            'content' => 'not yours',
        ])->assertOk()->json('message.id');

        $reply = $this->actingAs($me)->postJson("/api/chat/convs/{$convA->id}/send", [
            'content' => 'trying to fake a reply', 'parent_id' => $foreignMsgId,
        ])->assertOk()->json('message');

        $this->assertNull($reply['parentId']);
    }
}
