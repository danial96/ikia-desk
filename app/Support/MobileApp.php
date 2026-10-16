<?php

namespace App\Support;

/** Where people get the phone app. Links are set in .env (DESK_MOBILE_*); with none set, the "Download app" button stays hidden. */
class MobileApp
{
    /** @return array{android: ?string, ios: ?string, apk: ?string} */
    public static function links(): array
    {
        $clean = fn ($u) => is_string($u) && preg_match('#^https?://#i', $u) ? $u : null;   // never render anything but an http(s) link
        return [
            'android' => $clean(config('desk.mobile.store.android')),
            'ios'     => $clean(config('desk.mobile.store.ios')),
            'apk'     => $clean(config('desk.mobile.apk')),
        ];
    }

    public static function available(): bool
    {
        return (bool) array_filter(self::links());
    }
}
