<?php

namespace App\Support;

/** Release notes for the "new version available" banner (see resources/whatsnew.php). */
class WhatsNew
{
    private static function all(): array
    {
        $r = require resource_path('whatsnew.php');
        usort($r, fn ($a, $b) => $a['id'] <=> $b['id']);
        return $r;
    }

    public static function latestId(): int
    {
        $all = self::all();
        return $all ? (int) end($all)['id'] : 0;
    }

    /** Releases newer than $sinceId, oldest first, at most the newest $limit of them. */
    public static function since(int $sinceId, int $limit = 3): array
    {
        $newer = array_values(array_filter(self::all(), fn ($r) => $r['id'] > $sinceId));
        return array_slice($newer, -$limit);
    }
}
