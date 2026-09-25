<?php

namespace App\Support;

/** Turns any uploaded picture into a small square WebP (avatars are shown at 20–40px, so 192px is plenty). */
class AvatarImage
{
    public const SIZE = 192;

    /** @return string|null WebP bytes, or null when the image can't be read / GD lacks WebP */
    public static function toWebp(string $binary): ?string
    {
        if (!function_exists('imagewebp') || !($src = @imagecreatefromstring($binary))) return null;

        $w = imagesx($src); $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        // centre-crop to a square, then scale down
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagewebp($dst, null, 82);
        $out = ob_get_clean();
        imagedestroy($src); imagedestroy($dst);

        return $out ?: null;
    }
}
