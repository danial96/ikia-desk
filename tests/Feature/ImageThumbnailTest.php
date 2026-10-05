<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $f) @unlink($f);
        foreach (glob(Uploads::path('_thumbs') . '/*') ?: [] as $t) @unlink($t);
        parent::tearDown();
    }

    /** A noisy PNG (so it is genuinely large, like a real screenshot). */
    private function png(int $w, int $h, string $name = null): string
    {
        $name ??= 'tt_' . uniqid() . '.png';
        @mkdir(Uploads::path(), 0775, true);
        $im = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y += 2) {
            imagefilledrectangle($im, 0, $y, $w, $y + 1, imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            for ($k = 0; $k < 6; $k++) imagesetpixel($im, random_int(0, $w - 1), $y, random_int(0, 0xFFFFFF));
        }
        imagepng($im, Uploads::path($name), 0);              // no compression: reliably big
        imagedestroy($im);
        $this->made[] = Uploads::path($name);
        return $name;
    }

    private function me(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    public function test_a_large_image_comes_back_as_a_much_smaller_webp_preview_when_asked(): void
    {
        $name = $this->png(900, 1800);
        $original = filesize(Uploads::path($name));
        $this->assertGreaterThan(1000 * 1024, $original);

        $res = $this->actingAs($this->me())->get("/uploads/$name?s=400")->assertOk();

        $this->assertSame('image/webp', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $res->headers->get('Cache-Control'));
        $body = $res->baseResponse->getFile()->getPathname();
        [$w, $h] = getimagesize($body);
        $this->assertSame(400, max($w, $h));                                   // longest side capped
        $this->assertSame(200, $w);                                            // aspect ratio kept (900x1800)
        $this->assertLessThan($original / 10, filesize($body));                // an order of magnitude smaller
    }

    public function test_the_original_is_untouched_and_still_served_without_the_size_parameter(): void
    {
        $name = $this->png(900, 1800);
        $before = md5_file(Uploads::path($name));

        $this->actingAs($this->me())->get("/uploads/$name?s=300")->assertOk();
        $full = $this->actingAs($this->me())->get("/uploads/$name")->assertOk();

        $this->assertSame($before, md5_file(Uploads::path($name)));
        $this->assertSame($before, md5_file($full->baseResponse->getFile()->getPathname()));
        $this->assertNotSame('image/webp', $full->headers->get('Content-Type'));
    }

    public function test_the_preview_is_made_once_and_reused(): void
    {
        $name = $this->png(900, 1800);
        $me = $this->me();

        $this->actingAs($me)->get("/uploads/$name?s=400")->assertOk();
        $files = glob(Uploads::path('_thumbs') . '/*.webp');
        $this->assertCount(1, $files);
        $mtime = filemtime($files[0]);

        $this->actingAs($me)->get("/uploads/$name?s=400")->assertOk();
        $this->assertCount(1, glob(Uploads::path('_thumbs') . '/*.webp'));
        $this->assertSame($mtime, filemtime($files[0]));
    }

    public function test_images_that_are_already_small_or_not_shrinkable_are_served_as_they_are(): void
    {
        $small = 'tt_' . uniqid() . '.png';
        @mkdir(Uploads::path(), 0775, true);
        $im = imagecreatetruecolor(100, 80);
        imagepng($im, Uploads::path($small));
        $this->made[] = Uploads::path($small);

        $gif = 'tt_' . uniqid() . '.gif';
        imagegif($im, Uploads::path($gif));
        $this->made[] = Uploads::path($gif);

        $me = $this->me();
        foreach ([$small, $gif] as $f) {
            $res = $this->actingAs($me)->get("/uploads/$f?s=400")->assertOk();
            $this->assertNotSame('image/webp', $res->headers->get('Content-Type'));
        }
        $this->assertSame([], glob(Uploads::path('_thumbs') . '/*') ?: []);
    }

    public function test_nonsense_sizes_are_ignored_and_logged_out_people_get_nothing(): void
    {
        $name = $this->png(900, 1800);
        $me = $this->me();

        foreach (['abc', '5', '99999', '-1'] as $bad) {
            $res = $this->actingAs($me)->get("/uploads/$name?s=$bad")->assertOk();
            $this->assertNotSame('image/webp', $res->headers->get('Content-Type'));      // falls back to the original
        }

        auth()->logout();
        $this->get("/uploads/$name?s=400")->assertRedirect();
    }

    public function test_the_page_scripts_use_the_preview_for_inline_images_but_the_original_for_the_lightbox(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
        $html = $this->actingAs($user)->get('/chat')->assertOk()->getContent();

        $this->assertStringContainsString('window.imgThumb = function', $html);
        $this->assertStringContainsString('src="${escH(imgThumb(imgM[1], 560))}"', $html);
        $this->assertStringContainsString("imgLightbox(\${escH(JSON.stringify(imgM[1]))},0)", $html);   // click still opens the full-size original
    }
}
