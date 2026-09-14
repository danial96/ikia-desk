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
}
