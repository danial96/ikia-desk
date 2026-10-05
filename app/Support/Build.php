<?php

namespace App\Support;

/**
 * Identifies the deployed code (the git commit it was built from), so open browser tabs can tell when the
 * server has been updated and offer a reload. Falls back to file timestamps where there is no git checkout.
 */
class Build
{
    private static ?string $cached = null;
    private static int $cachedAt = 0;

    public static function id(): string
    {
        // a few requests per second at most re-read two tiny files; no need to do it more than every 10s
        if (self::$cached !== null && time() - self::$cachedAt < 10) return self::$cached;

        self::$cachedAt = time();
        return self::$cached = self::fromGit() ?? self::fromFiles();
    }

    private static function fromGit(): ?string
    {
        $head = @file_get_contents(base_path('.git/HEAD'));
        if ($head === false) return null;
        $head = trim($head);

        if (!str_starts_with($head, 'ref: ')) return preg_match('/^[0-9a-f]{40}$/', $head) ? substr($head, 0, 12) : null;   // detached HEAD

        $ref = substr($head, 5);
        $sha = @file_get_contents(base_path('.git/' . $ref));
        if ($sha !== false && preg_match('/^[0-9a-f]{40}/', trim($sha))) return substr(trim($sha), 0, 12);

        // refs can live in packed-refs instead of their own file
        foreach (@file(base_path('.git/packed-refs'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_ends_with($line, ' ' . $ref) && preg_match('/^([0-9a-f]{40}) /', $line, $m)) return substr($m[1], 0, 12);
        }
        return null;
    }

    private static function fromFiles(): string
    {
        $stamp = '';
        foreach ([resource_path('views/layouts/app.blade.php'), base_path('routes/web.php'), public_path('build/manifest.json')] as $f) {
            $stamp .= (string) @filemtime($f) . '|';
        }
        return substr(md5($stamp), 0, 12);
    }
}
