<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SvgAndPasteTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_svg_attachment_shows_as_a_picture_but_cannot_run_script(): void
    {
        @mkdir(Uploads::path(), 0775, true);
        $name = 'tt_' . uniqid() . '.svg';
        file_put_contents(Uploads::path($name), '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');
        try {
            $u   = User::factory()->create(['is_active' => true]);
            $res = $this->actingAs($u)->get("/uploads/$name?name=logo.svg")->assertOk();

            $this->assertStringContainsString('image/svg+xml', $res->headers->get('Content-Type'));        // an <img> can draw it
            $this->assertStringStartsWith('inline', $res->headers->get('Content-Disposition'));
            $csp = $res->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("default-src 'none'", $csp);                                  // opened on its own it may not run script
            $this->assertStringContainsString('sandbox', $csp);
            $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));

            // html and xml stay downloads
            $h = 'tt_' . uniqid() . '.html';
            file_put_contents(Uploads::path($h), '<script>alert(1)</script>');
            $this->actingAs($u)->get("/uploads/$h")->assertOk();
            $this->assertStringStartsWith('attachment', $this->actingAs($u)->get("/uploads/$h")->headers->get('Content-Disposition'));
            @unlink(Uploads::path($h));
        } finally {
            @unlink(Uploads::path($name));
        }
    }

    public function test_formatted_paste_is_converted_for_the_task_comment_box_and_the_description_editor(): void
    {
        $u    = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();

        $this->assertStringContainsString('window.htmlToMarkup = function (html)', $html);
        $this->assertStringContainsString('tp-comment-text|tpe-desc', $html);                              // the plain boxes get the markup inserted
        $this->assertStringContainsString("var md = html && window.htmlToMarkup && window.PASTE_HAS_FORMAT.test(html) ? window.htmlToMarkup(html) : '';", $html);   // and the description editor
        $this->assertStringContainsString("'[url=' + href + ']'", $html);
        $this->assertStringContainsString("wrapLines(inner, '[b]', '[/b]')", $html);
        // an SVG now opens in its own tab like any picture instead of being forced to download
        $this->assertStringContainsString("'bmp','svg','avif'", $html);
    }
}
