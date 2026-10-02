<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Realtime push (chat / notifications). Leave unset to fall back to plain polling.
    'pusher' => [
        'app_id'  => env('PUSHER_APP_ID'),
        'key'     => env('PUSHER_APP_KEY'),
        'secret'  => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER'),
        'channel_prefix' => env('PUSHER_CHANNEL_PREFIX'),
    ],

    // Voice calls (WebRTC). STUN alone connects most networks; a TURN relay is what makes the rest work
    // (strict offices, mobile carriers). Either give static TURN credentials, or a Cloudflare Calls TURN key.
    'webrtc' => [
        'stun'            => env('WEBRTC_STUN', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302'),
        'turn_urls'       => env('WEBRTC_TURN_URLS'),          // comma separated, e.g. turn:host:3478,turns:host:5349
        'turn_username'   => env('WEBRTC_TURN_USERNAME'),
        'turn_credential' => env('WEBRTC_TURN_CREDENTIAL'),
        'cf_turn_key_id'  => env('CLOUDFLARE_TURN_KEY_ID'),
        'cf_turn_token'   => env('CLOUDFLARE_TURN_API_TOKEN'),
    ],

    // Browser push notifications (alerts while Desk is closed). Generate keys with `php artisan webpush:keys`.
    'webpush' => [
        'public_key'  => env('WEBPUSH_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_PRIVATE_KEY'),
        'subject'     => env('WEBPUSH_SUBJECT', 'mailto:' . env('MAIL_FROM_ADDRESS', 'hello@example.com')),
    ],

];
