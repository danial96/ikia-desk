<?php

namespace Tests\Feature;

use App\Support\AvatarImage;
use Tests\TestCase;

class AvatarWebpTest extends TestCase
{
    public function test_any_picture_becomes_a_small_square_webp(): void
    {
        $im = imagecreatetruecolor(800, 400);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 30));
        ob_start(); imagepng($im); $png = ob_get_clean();

        $webp = AvatarImage::toWebp($png);

        $this->assertNotNull($webp);
        $info = getimagesizefromstring($webp);
        $this->assertSame([AvatarImage::SIZE, AvatarImage::SIZE], [$info[0], $info[1]]);
        $this->assertSame('image/webp', $info['mime']);
        $this->assertLessThan(strlen($png), strlen($webp) + 1);
    }

    public function test_garbage_is_rejected_not_crashed(): void
    {
        $this->assertNull(AvatarImage::toWebp('not an image'));
    }
}
