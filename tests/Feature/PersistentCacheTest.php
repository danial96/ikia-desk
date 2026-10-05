<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistentCacheTest extends TestCase
{
    use RefreshDatabase;

    private function upload(): string
    {
        @mkdir(Uploads::path(), 0775, true);
        $name = 'tt_' . uniqid() . '.png';
        $im = imagecreatetruecolor(40, 30);
        imagepng($im, Uploads::path($name));
        return $name;
    }

    public function test_uploaded_files_are_kept_by_the_browser_but_never_by_a_shared_cache(): void
    {
        $name = $this->upload();
        try {
            $res = $this->actingAs(User::factory()->create(['is_active' => true]))->get("/uploads/$name?name=pic.png")->assertOk();

            $cc = $res->headers->get('Cache-Control');
            $this->assertStringContainsString('private', $cc);           // browser only: a CDN must never store signed-in-only files
            $this->assertStringNotContainsString('public', $cc);
            $this->assertStringContainsString('max-age=2592000', $cc);   // 30 days
            $this->assertStringContainsString('immutable', $cc);
            $this->assertNotEmpty($res->headers->get('ETag'));
        } finally {
            @unlink(Uploads::path($name));
        }
    }

    public function test_asking_again_with_the_validator_gets_a_304_with_no_body(): void
    {
        $name = $this->upload();
        try {
            $u = User::factory()->create(['is_active' => true]);
            $etag = $this->actingAs($u)->get("/uploads/$name")->headers->get('ETag');

            $this->actingAs($u)->get("/uploads/$name", ['If-None-Match' => $etag])->assertStatus(304);
            $this->actingAs($u)->get("/uploads/$name", ['If-None-Match' => '"something-else"'])->assertOk();
        } finally {
            @unlink(Uploads::path($name));
        }
    }

    public function test_pages_remember_chats_the_chat_list_and_tasks_between_page_loads(): void
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);

        $missing = [];
        $check = function (string $html, array $needles) use (&$missing) {
            foreach ($needles as $n) if (!str_contains($html, $n)) $missing[] = $n;
        };

        $check($this->actingAs($u)->get('/chat')->assertOk()->getContent(), [
            'window.LocalCache = (function',
            "LocalCache.get('msgs', id)",                          // reopening a chat shows its messages at once
            "LocalCache.set('convs', 'all', _cpAllConvs)",         // the chat list too
            "LocalCache.get('convs', 'all')",
            "(LocalCache.get('msgs', id) || {}).messages",         // and the popup chat
        ]);
        $check($this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent(), [
            "LocalCache.get('task', cacheKey)",
            "LocalCache.set('task', cacheKey, data)",
        ]);

        $this->assertSame([], $missing);
    }

    public function test_the_remembered_data_is_per_user_bounded_and_wiped_at_logout(): void
    {
        $html = $this->actingAs(User::factory()->create(['is_active' => true]))->get('/chat')->assertOk()->getContent();

        $this->assertStringContainsString("'ikia.c.' + (window.ME_ID || 0) + '.'", $html);            // one person's data is never read for another
        $this->assertStringContainsString('const LIMITS = { msgs: 12, task: 12, convs: 1 };', $html);   // only a few entries are kept
        $this->assertStringContainsString('if (window.LocalCache) LocalCache.clear();', $html);         // logging out wipes it
    }
}
