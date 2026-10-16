<?php

return [
    // Opened from the sidebar's "Payment Terminal" tab, only for people with that permission.
    'payment_terminal_url' => env('PAYMENT_TERMINAL_URL', 'https://securepaymentterminal.digitalservicescorp.com/'),

    // Mobile app (the /api/v1 API). Builds older than min_version are told to update (HTTP 426).
    'mobile' => [
        'min_version'    => env('DESK_MOBILE_MIN_VERSION', '0.0.0'),
        'latest_version' => env('DESK_MOBILE_LATEST_VERSION', '0.0.0'),
        // A direct .apk download for Android phones that install outside Google Play.
        'apk'            => env('DESK_MOBILE_APK_URL'),
        'store' => [
            'android' => env('DESK_MOBILE_ANDROID_URL'),
            'ios'     => env('DESK_MOBILE_IOS_URL'),
        ],
    ],
];
