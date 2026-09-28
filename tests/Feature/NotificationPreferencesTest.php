<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_turn_off_message_and_task_notifications_and_sounds_independently(): void
    {
        $user = User::factory()->create(['is_active' => true])->fresh();
        // Defaults are all on.
        $this->assertTrue($user->notify_messages);
        $this->assertTrue($user->notify_tasks_sound);

        $this->actingAs($user)->postJson(route('profile.notifications'), [
            'notify_messages'       => false,
            'notify_messages_sound' => true,
            'notify_tasks'          => true,
            'notify_tasks_sound'    => false,
        ])->assertOk()->assertJson([
            'ok' => true,
            'notify_messages' => false,
            'notify_messages_sound' => true,
            'notify_tasks' => true,
            'notify_tasks_sound' => false,
        ]);

        $user->refresh();
        $this->assertFalse($user->notify_messages);
        $this->assertTrue($user->notify_messages_sound);
        $this->assertTrue($user->notify_tasks);
        $this->assertFalse($user->notify_tasks_sound);
    }

    public function test_omitted_checkboxes_are_saved_as_off_like_a_real_unchecked_checkbox(): void
    {
        // Unchecked HTML checkboxes simply aren't present in the request at all.
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->postJson(route('profile.notifications'), [])->assertOk();

        $user->refresh();
        $this->assertFalse($user->notify_messages);
        $this->assertFalse($user->notify_messages_sound);
        $this->assertFalse($user->notify_tasks);
        $this->assertFalse($user->notify_tasks_sound);
    }
}
