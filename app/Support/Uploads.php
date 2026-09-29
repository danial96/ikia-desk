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
}
