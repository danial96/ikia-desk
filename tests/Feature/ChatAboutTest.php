<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAboutTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_lists_links_media_files_and_shared_tasks(): void
    {
        $a = User::factory()->create(['is_active' => true]);
        $b = User::factory()->create(['is_active' => true, 'position' => 'Head of Production']);
        $c = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        $c->members()->attach([$a->id, $b->id]);

        Message::create(['conversation_id' => $c->id, 'user_id' => $a->id, 'content' => 'look https://example.com/page and more']);
        Message::create(['conversation_id' => $c->id, 'user_id' => $b->id, 'content' => "[img]/uploads/bitrix/chat/one.png[/img]\ncaption"]);
        Message::create(['conversation_id' => $c->id, 'user_id' => $b->id, 'content' => '[file name="Plan.pdf"]/uploads/bitrix/chat/plan.pdf[/file]']);
        Task::create(['title' => 'Shared job', 'created_by' => $a->id, 'assigned_to' => $b->id, 'priority' => 'medium', 'status' => 'new']);
        Task::create(['title' => 'Unrelated', 'created_by' => $a->id, 'assigned_to' => $a->id, 'priority' => 'medium', 'status' => 'new']);

        $r = $this->actingAs($a)->getJson("/api/chat/convs/{$c->id}/about")->assertOk()->json();
        $this->assertSame($b->name, $r['name']);
        $this->assertSame('Head of Production', $r['subtitle']);
        $this->assertSame(['https://example.com/page'], array_column($r['links'], 'url'));   // upload paths are not "links"
        $this->assertCount(2, $r['media']);
        $this->assertEqualsCanonicalizing(['img', 'file'], array_column($r['media'], 'type'));
        $this->assertSame(['Shared job'], array_column($r['tasks'], 'title'));

        $x = User::factory()->create(['is_active' => true]);
        $this->actingAs($x)->getJson("/api/chat/convs/{$c->id}/about")->assertStatus(403);
    }
}
