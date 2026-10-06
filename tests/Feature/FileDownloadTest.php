<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function makeUpload(string $ext, string $body = 'x'): string
    {
        @mkdir(Uploads::path(), 0775, true);
        $name = 'tt_' . uniqid() . '.' . $ext;
        file_put_contents(Uploads::path($name), $body);
        return $name;
    }

    public function test_files_a_browser_cannot_show_are_sent_as_downloads_and_media_stays_inline(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $made = [];
        try {
            foreach (['sql' => 'attachment', 'txt' => 'attachment', 'zip' => 'attachment', 'pdf' => 'inline', 'png' => 'inline', 'mp3' => 'inline'] as $ext => $want) {
                $name = $made[] = $this->makeUpload($ext);
                $res = $this->actingAs($u)->get("/uploads/$name?name=database.$ext")->assertOk();
                $cd = $res->headers->get('Content-Disposition');
                $this->assertStringStartsWith($want, $cd, "$ext should be $want, got $cd");
                $this->assertStringContainsString("database.$ext", $cd);
                $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
            }
        } finally {
            foreach ($made as $n) @unlink(Uploads::path($n));
        }
    }

    public function test_every_attachment_chip_uses_the_download_aware_link_attributes(): void
    {
        $root = dirname(__DIR__, 2) . '/resources/views/';
        $layout = file_get_contents($root . 'layouts/app.blade.php');
        $this->assertStringContainsString('window.fileLinkAttrs = function', $layout);
        $this->assertStringContainsString("' download=\"'", $layout);

        foreach (['tasks/_task_panel.blade.php', 'chat/index.blade.php', 'layouts/app.blade.php', 'tasks/index.blade.php', 'tasks/kanban.blade.php'] as $f) {
            $this->assertStringContainsString('fileLinkAttrs(', file_get_contents($root . $f), "$f must use fileLinkAttrs for file chips");
        }
        // the task comment chip (what people click in a task's comments) in particular
        $panel = file_get_contents($root . 'tasks/_task_panel.blade.php');
        $this->assertMatchesRegularExpression('/fileViewHref\(url\):url\}"\$\{window\.fileLinkAttrs\?fileLinkAttrs\(url,f\.name\)/', $panel);
    }
}
