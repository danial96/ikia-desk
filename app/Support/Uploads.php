<?php

namespace App\Support;

/**
 * Uploaded and imported files live outside the web root (storage/app/private/uploads).
 * Their URLs keep the public-looking "/uploads/..." form (stored in task_files.disk_path and
 * message content) and are served by the authenticated "uploads.show" route.
 */
class Uploads
{
    public static function path(string $relative = ''): string
    {
        $relative = ltrim($relative, '/');
        return storage_path('app/private/uploads' . ($relative !== '' ? '/' . $relative : ''));
    }

    /** Absolute path for a "/uploads/..." relative path, or null if it escapes the uploads root or doesn't exist. */
    public static function resolve(string $relative): ?string
    {
        if (str_contains($relative, '..') || str_contains($relative, "\0")) return null;

        $root = realpath(self::path());
        $full = realpath(self::path($relative));
        if ($root === false || $full === false || !is_file($full)) return null;

        return str_starts_with($full, $root . DIRECTORY_SEPARATOR) ? $full : null;
    }

    /**
     * Public "/uploads/..." URL for a stored relative path, with the original filename appended
     * as a decorative trailing path segment (not just a query string) — a browser's own Save-As
     * can default to the URL's last path segment and ignore Content-Disposition entirely (Chrome
     * does this for an image viewed as its own tab), so the real name needs to live in the path.
     */
    public static function urlWithName(string $relative, ?string $originalName): string
    {
        $url = asset($relative);
        if (!$originalName) return $url;
        $clean = basename(str_replace(['/', '\\'], '', $originalName));
        if ($clean === '') return $url;
        return $url . '/' . rawurlencode($clean) . '?name=' . rawurlencode($clean);
    }

    /**
     * Resolves a relative "/uploads/..." path that may carry the decorative trailing
     * "/Original Name.ext" segment urlWithName() appends. Returns [absolute path, nice name],
     * either of which is null on failure / when there was no decorative segment to read.
     */
    public static function resolveWithName(string $relative): array
    {
        $abs = self::resolve($relative);
        if ($abs) return [$abs, null];
        if (str_contains($relative, '/')) {
            $dir = pathinfo($relative, PATHINFO_DIRNAME);
            $tail = pathinfo($relative, PATHINFO_BASENAME);
            $abs = self::resolve($dir);
            if ($abs) return [$abs, $tail];
        }
        return [null, null];
    }

    /**
     * A small cached copy of an image for showing inline (chat bubbles, comments, tiles), so opening a task with
     * a few 1-2MB screenshots doesn't download and decode 9MB. Returns null when the original should be served
     * as is (not a shrinkable image type, already small, GD unavailable, or too big to process safely).
     * The original file is never touched; the lightbox and downloads still use it.
     */
    public static function thumbnail(string $full, int $maxSide): ?string
    {
        $maxSide = max(96, min(1600, $maxSide));
        if (!in_array(strtolower(pathinfo($full, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'bmp'], true)) return null;
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) return null;

        $info = @getimagesize($full);
        if (!$info || $info[0] < 1 || $info[1] < 1) return null;
        [$w, $h, $type] = $info;
        if ($w * $h > 40000000) return null;                                        // the decoded bitmap would need ~160MB
        if (max($w, $h) <= $maxSide && filesize($full) < 200 * 1024) return null;   // already small: nothing to gain

        $dir = self::path('_thumbs');
        $out = $dir . '/' . sha1($full . '|' . filemtime($full) . '|' . filesize($full)) . '_' . $maxSide . '.webp';
        if (is_file($out)) return $out;

        $load = [IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_WEBP => 'imagecreatefromwebp', IMAGETYPE_BMP => 'imagecreatefrombmp'][$type] ?? null;
        if (!$load || !function_exists($load)) return null;

        @ini_set('memory_limit', '512M');
        $src = @$load($full);
        if (!$src) return null;

        // phones store rotated photos with an orientation flag; honour it so the preview isn't sideways
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($full)['Orientation'] ?? 1;
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle && ($rot = imagerotate($src, $angle, 0))) { imagedestroy($src); $src = $rot; [$w, $h] = [imagesx($src), imagesy($src)]; }
        }

        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $tmp = $out . '.' . getmypid() . '.tmp';
        $ok = imagewebp($dst, $tmp, 85);
        imagedestroy($dst);
        if (!$ok || !@rename($tmp, $out)) { @unlink($tmp); return null; }
        return $out;
    }
}
