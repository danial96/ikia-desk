<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Images are always shown and served in their original quality: no previews, no resizing, no recompression. */
class OriginalImageQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inline_images_use_the_original_file_in_every_view(): void
    {
        $u = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        foreach (['/chat', '/tasks/kanban?status=in_progress'] as $url) {
            $html = $this->actingAs($u)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('imgThumb', $html);
            $this->assertStringContainsString('src="${escH(imgM[1])}"', $html);
        }
    }

    public function test_the_uploads_route_serves_the_untouched_original_even_if_asked_for_a_size(): void
    {
        @mkdir(Uploads::path(), 0775, true);
        $name = 'tt_' . uniqid() . '.png';
        $im = imagecreatetruecolor(900, 1800);
        for ($y = 0; $y < 1800; $y += 2) imagefilledrectangle($im, 0, $y, 900, $y + 1, imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        imagepng($im, Uploads::path($name), 0);
        imagedestroy($im);
        $before = md5_file(Uploads::path($name));

        try {
            $u = User::factory()->create(['is_active' => true]);
            foreach (["/uploads/$name", "/uploads/$name?s=300"] as $url) {
                $res = $this->actingAs($u)->get($url)->assertOk();
                $this->assertNotSame('image/webp', $res->headers->get('Content-Type'));
                $this->assertSame($before, md5_file($res->baseResponse->getFile()->getPathname()));   // byte for byte the original
            }
            $this->assertSame([], glob(Uploads::path('_thumbs') . '/*') ?: []);      // nothing is ever generated
        } finally {
            @unlink(Uploads::path($name));
        }
    }
}
