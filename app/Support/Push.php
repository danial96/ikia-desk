<?php

namespace App\Support;

/**
 * "Tell these people" in every way we can: browser push (WebPush) and the phone app (MobilePush).
 * Callers say what happened once; this decides who gets it where.
 */
class Push
{
    public static function enabled(): bool
    {
        return WebPush::enabled() || MobilePush::enabled();
    }

    public static function sendToUsers(array $userIds, array $payload): void
    {
        WebPush::sendToUsers($userIds, $payload);
        MobilePush::sendToUsers($userIds, $payload);
    }
}
