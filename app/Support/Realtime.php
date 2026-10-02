<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Pusher Channels client: publishes "something changed" signals to per-user private
 * channels so browsers learn about new chat messages / notifications instantly instead of
 * polling every few seconds.
 *
 * Deliberately no SDK (composer isn't available on the production host) — Pusher's REST publish
 * and private-channel auth are each one HMAC. Payloads only ever carry ids ("conversation 231 has a
 * new message 5678"), never message text: the browser fetches the content from our own server.
 *
 * Everything is a no-op until PUSHER_APP_ID/KEY/SECRET/CLUSTER are set, so the app keeps working
 * exactly as before (plain polling) when it isn't configured or Pusher is unreachable.
 */
class Realtime
{
    public static function enabled(): bool
    {
        $c = config('services.pusher');
        return !empty($c['app_id']) && !empty($c['key']) && !empty($c['secret']) && !empty($c['cluster']);
    }

    /** What the browser needs to open its socket, or null when realtime isn't configured. */
    public static function clientConfig(): ?array
    {
        return self::enabled()
            ? ['key' => config('services.pusher.key'), 'cluster' => config('services.pusher.cluster')]
            : null;
    }

    public static function userChannel(int $userId): string
    {
        return 'private-user.' . $userId;
    }

    /** Pusher private-channel auth token: "<key>:<hmac-sha256(socket_id:channel)>". */
    public static function authToken(string $socketId, string $channel): string
    {
        return config('services.pusher.key') . ':' . hash_hmac('sha256', $socketId . ':' . $channel, config('services.pusher.secret'));
    }

    /**
     * Queue a signal for these users. It is sent after the HTTP response has been flushed to the
     * browser, so a slow or failing Pusher call can never delay or break the action that triggered it.
     */
    public static function publishToUsers(array $userIds, string $event, array $data = []): void
    {
        if (!self::enabled()) return;
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        if (!$ids) return;
        $sent = false;   // terminate callbacks can run more than once on a long-lived app instance
        app()->terminating(function () use (&$sent, $ids, $event, $data) {
            if ($sent) return;
            $sent = true;
            self::publishNow($ids, $event, $data);
        });
    }

    /** Synchronous publish (used by the terminating hook above and by tests). Never throws. */
    public static function publishNow(array $userIds, string $event, array $data = []): void
    {
        if (!self::enabled()) return;
        $cfg = config('services.pusher');
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        foreach (array_chunk($userIds, 100) as $chunk) {           // Pusher allows 100 channels per event
            try {
                $body = json_encode([
                    'name'     => $event,
                    'channels' => array_map([self::class, 'userChannel'], $chunk),
                    'data'     => json_encode($data),
                ]);
                $path   = '/apps/' . $cfg['app_id'] . '/events';
                $params = [
                    'auth_key'       => $cfg['key'],
                    'auth_timestamp' => time(),
                    'auth_version'   => '1.0',
                    'body_md5'       => md5($body),
                ];
                ksort($params);
                $query = urldecode(http_build_query($params));
                $params['auth_signature'] = hash_hmac('sha256', "POST\n" . $path . "\n" . $query, $cfg['secret']);

                $res = Http::timeout(3)->withBody($body, 'application/json')
                    ->post('https://api-' . $cfg['cluster'] . '.pusher.com' . $path . '?' . http_build_query($params));
                if (!$res->successful()) {
                    Log::warning('Realtime publish rejected', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 200)]);
                }
            } catch (\Throwable $e) {
                Log::warning('Realtime publish failed: ' . $e->getMessage());
            }
        }
    }
}
