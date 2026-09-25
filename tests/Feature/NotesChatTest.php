<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_user_gets_a_private_notes_chat_listed_first(): void
    {
        $a = User::factory()->create(['is_active' => true]);
        $b = User::factory()->create(['is_active' => true]);

        $resA = $this->actingAs($a)->getJson('/api/chat/convs')->assertOk();
        $resA->assertJsonPath('convs.0.type', 'notes')->assertJsonPath('convs.0.name', 'Notes');
        $notesId = $resA->json('convs.0.id');

        // idempotent: a second load doesn't create another one
        $this->actingAs($a)->getJson('/api/chat/convs')->assertJsonCount(1, 'convs');

        // can write in it, and it is invisible to everybody else
        $this->actingAs($a)->postJson("/api/chat/convs/$notesId/send", ['content' => 'my secret'])->assertOk();
        $this->actingAs($b)->getJson("/api/chat/convs/$notesId/msgs")->assertStatus(403);
        $this->actingAs($b)->getJson('/api/chat/convs')->assertJsonMissing(['name' => 'my secret']);
        $this->assertNotSame($notesId, $this->actingAs($b)->getJson('/api/chat/convs')->json('convs.0.id'));
    }
}
