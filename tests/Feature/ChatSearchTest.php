<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_messages_in_that_conversation_only_and_hides_it_from_outsiders(): void
    {
        $a = User::factory()->create(['is_active' => true]);
        $b = User::factory()->create(['is_active' => true]);
        $x = User::factory()->create(['is_active' => true]);

        $c1 = Conversation::create(['type' => 'direct', 'created_by' => $a->id]); $c1->members()->attach([$a->id, $b->id]);
        $c2 = Conversation::create(['type' => 'direct', 'created_by' => $a->id]); $c2->members()->attach([$a->id, $x->id]);

        Message::create(['conversation_id' => $c1->id, 'user_id' => $a->id, 'content' => 'the invoice is ready']);
        Message::create(['conversation_id' => $c1->id, 'user_id' => $b->id, 'content' => 'see [img]/uploads/x.png[/img] invoice screenshot']);
        Message::create(['conversation_id' => $c1->id, 'user_id' => $b->id, 'content' => 'unrelated']);
        Message::create(['conversation_id' => $c2->id, 'user_id' => $x->id, 'content' => 'invoice in another chat']);

        $r = $this->actingAs($a)->getJson("/api/chat/convs/{$c1->id}/search?q=invoice")->assertOk()->json();
        $this->assertCount(2, $r['results']);
        $this->assertStringContainsString('Photo', collect($r['results'])->pluck('text')->implode(' '));   // [img] tags never leak into results
        $this->assertStringNotContainsString('[img]', json_encode($r));

        $this->actingAs($a)->getJson("/api/chat/convs/{$c1->id}/search?q=i")->assertOk()->assertJson(['results' => []]);   // too short
        $this->actingAs($x)->getJson("/api/chat/convs/{$c1->id}/search?q=invoice")->assertStatus(403);
        $this->actingAs($a)->getJson("/api/chat/convs/{$c1->id}/search?q=100%25")->assertOk()->assertJson(['results' => []]);   // % is literal, not a wildcard
    }
}
