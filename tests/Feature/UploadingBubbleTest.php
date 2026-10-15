<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadingBubbleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_photo_being_sent_is_taken_out_of_the_uploading_bubble_so_it_does_not_show_twice(): void
    {
        $u    = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        $this->assertStringContainsString("it.st !== 'sent' && it.st !== 'sending'", $html);
        $this->assertStringContainsString("it.st = 'sending'; paint();", $html);
        $this->assertStringContainsString("if (!list.length) { pending.style.display = 'none'; return; }", $html);
    }
}
