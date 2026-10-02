<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
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

                $url = 'https://api-' . $cfg['cluster'] . '.pusher.com' . $path . '?' . http_build_query($params);
                if (self::detachedPost($url, $body)) continue;

                $res = Http::timeout(3)->withBody($body, 'application/json')->post($url);
                if (!$res->successful()) {
                    Log::warning('Realtime publish rejected', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 200)]);
                }
            } catch (\Throwable $e) {
                Log::warning('Realtime publish failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Fire the POST from a detached background `curl` and return immediately.
     *
     * Production runs PHP as plain FastCGI (cgi-fcgi), which has no fastcgi_finish_request, so even
     * work queued "after the response" still held the request open: the Pusher round trip (~480ms,
     * Germany -> Mumbai incl. TLS setup) was being added to every message send. Spawning a child
     * costs ~2ms and the child outlives the request. Returns false when that isn't possible
     * (tests, Windows, exec disabled, no curl) and the caller falls back to an in-process request.
     * The URL/body visible in `ps` for a moment carry only ids and a signature over them, never the secret.
     */
    private static function detachedPost(string $url, string $body): bool
    {
        if (app()->runningUnitTests() || PHP_OS_FAMILY === 'Windows' || !function_exists('exec')) return false;
        $curl = self::curlBinary();
        if (!$curl) return false;
        @exec(self::curlCommand($curl, $url, $body), $unused, $code);
        return $code === 0;
    }

    public static function curlCommand(string $curl, string $url, string $body): string
    {
        return escapeshellarg($curl) . ' -sS -m 10 -X POST -H ' . escapeshellarg('Content-Type: application/json')
            . ' --data-binary ' . escapeshellarg($body) . ' ' . escapeshellarg($url) . ' > /dev/null 2>&1 &';
    }

    /** Path of a working curl, remembered for an hour (an empty string is cached too, so we don't re-probe every request). */
    private static function curlBinary(): ?string
    {
        $path = Cache::remember('realtime.curl_path', 3600, function () {
            // open_basedir hides /usr/bin from is_executable(), so ask the shell instead
            return trim((string) @shell_exec('command -v curl 2>/dev/null'));
        });
        return $path !== '' ? $path : null;
    }
}
