<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

/**
 * All dates are stored (and always were) as naive wall-clock values in the app's own canonical
 * timezone (config('app.timezone'), Asia/Karachi) — there's no offset in the DB column, so a raw
 * "2026-09-28 18:00:00" only means something once you know whose clock it was read off.
 *
 * A person types a deadline on their own clock ("6pm" — their own wall-clock, in whatever
 * timezone they set on their profile). toApp() converts that into the equivalent instant in the
 * app's canonical zone before it's stored, so every viewer afterwards is converting from the same
 * true instant. forViewer() does the reverse for display: take the canonical-zone value and show
 * it in whichever timezone the CURRENT viewer chose, so two people looking at the same task see
 * the same instant translated into their own clock, not the same digits.
 */
class Tz
{
    public static function toApp(?string $raw, ?User $enteredBy = null): ?Carbon
    {
        if (!$raw) return null;
        $enteredBy ??= auth()->user();
        $tz = $enteredBy?->viewTz() ?? config('app.timezone');
        try {
            return Carbon::parse($raw, $tz)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    public static function forViewer(?Carbon $value, ?User $viewer = null): ?Carbon
    {
        if (!$value) return null;
        $viewer ??= auth()->user();
        $tz = $viewer?->viewTz() ?? config('app.timezone');
        return $value->copy()->setTimezone($tz);
    }
}
