<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFormDescriptionImagesTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        return $this->actingAs($u)->get('/tasks/kanban?status=in_progress')->assertOk()->getContent();
    }

    public function test_the_description_is_an_editor_where_images_sit_in_the_text_and_it_is_twice_as_tall(): void
    {
        $html = $this->page();

        // visible editor + hidden textarea that keeps the plain-text source of truth ([img] markers at the same positions)
        $this->assertStringContainsString('id="nt-desc-ed" class="is-empty" contenteditable="true"', $html);
        $this->assertStringContainsString('<textarea name="description" id="nt-desc-ta" hidden', $html);
        $this->assertStringContainsString('#nt-desc-ed { display:block; width:100%; min-height:220px;', $html);   // twice the old 110px
        $this->assertStringContainsString('function ntEdSerialize(root)', $html);
        $this->assertStringContainsString('function ntEdRender(text)', $html);
        // writing the textarea's value (draft restore, edit prefill, reset) redraws the editor
        $this->assertStringContainsString("set: function(v) { _taValue.set.call(ta, v); ntEdRender(v); }", $html);
        // the picker, drop and paste all upload through the same function that places the image at the caret
        $this->assertStringContainsString('window.ntAddFile = async function(file, inline)', $html);
        $this->assertSame(2, substr_count($html, 'await ntAddFile(files[i], true)') - 1);   // picker + drop (paste uses an async wrapper)
        $this->assertStringContainsString("ed.addEventListener('paste'", $html);
        // the Files list under the box is still drawn as before
        $this->assertStringContainsString("'<span style=\"font-size:13px;font-weight:600;color:#374151;\">Files: '", $html);
    }

    public function test_saving_does_not_append_pictures_the_text_already_carries(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('a.url && curDesc.indexOf(a.url) === -1', $html);
    }

    public function test_a_saved_task_draws_description_pictures_where_they_were_placed(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('(\\[disk\\s+file\\s+id=n\\d+[^\\]]*\\]|\\[img\\][\\s\\S]*?\\[\\/img\\])', $html);
        $this->assertStringContainsString("const inlineKey = inlineUrls.length && window.registerGallery", $html);
        // editing keeps the [img] markers in the text
        $this->assertStringContainsString("m => '\\u0001' + (keep.push(m) - 1) + '\\u0002'", $html);
    }
}
