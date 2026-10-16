<?php

namespace App\Support;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push notifications to the phone app, through Expo's push service (https://docs.expo.dev/push-notifications/sending-notifications/).
 *
 * Expo carries the message on to Apple (APNs) and Google (FCM) for us, so the server needs no Firebase or Apple secrets:
 * the app registers an "ExponentPushToken[...]" (stored in device_tokens) and this class POSTs to Expo's API.
 * Pure PHP, no package (production has no composer). Never throws: a failed push must never break the action that caused it.
 */
class MobilePush
{
    public const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public static function enabled(): bool
    {
        return (bool) config('services.expo_push.enabled', true);
    }

    /** Only Expo tokens can be sent through Expo; anything else a client registered is ignored. */
    public static function isExpoToken(string $t): bool
    {
        return (bool) preg_match('/^(Exponent|Expo)PushToken\[[^\]\s]+\]$/', $t);
    }

    /**
     * Push $payload (title/body/url/type/tag, the same shape WebPush takes) to every phone of the given users.
     * Voice-call rings are website-only for now.
     */
    public static function sendToUsers(array $userIds, array $payload): void
    {
        if (!self::enabled() || ($payload['type'] ?? '') === 'call') return;
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) return;

        try {
            $tokens = DeviceToken::whereIn('user_id', $userIds)->get()->filter(fn ($d) => self::isExpoToken($d->push_token));
            if ($tokens->isEmpty()) return;

            $messages = $tokens->map(fn ($d) => self::message($d->push_token, $payload))->values()->all();

            foreach (array_chunk($messages, 100) as $chunk) {      // Expo accepts 100 messages per request
                $body = json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (Realtime::detachedAvailable()) {
                    @exec(self::detachedCommand($body));
                    continue;
                }
                $res = Http::timeout(5)->withHeaders(self::headers())->withBody($body, 'application/json')->post(self::ENDPOINT);
                if ($res->successful()) self::forgetDeadTokens($chunk, (array) $res->json('data'));
                else Log::warning('Expo push rejected', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 200)]);
            }
        } catch (\Throwable $e) {
            Log::warning('MobilePush failed: ' . $e->getMessage());
        }
    }

    /** One Expo message. The app opens the right screen from data.conversation_id / data.task_id (or the website-style url). */
    public static function message(string $to, array $payload): array
    {
        $url  = (string) ($payload['url'] ?? '');
        $type = (string) ($payload['type'] ?? 'message');
        $data = ['url' => $url, 'type' => $type];
        if (preg_match('/[?&]conv=(\d+)/', $url, $m)) $data['conversation_id'] = (int) $m[1];
        if (preg_match('/[?&]task=(\d+)/', $url, $m)) $data['task_id'] = (int) $m[1];

        return array_filter([
            'to'        => $to,
            'title'     => (string) ($payload['title'] ?? 'IKIA Desk'),
            'body'      => (string) ($payload['body'] ?? ''),
            'data'      => $data,
            'sound'     => 'default',
            'priority'  => 'high',
            'channelId' => $type === 'task' ? 'tasks' : 'messages',
            'ttl'       => (int) ($payload['ttl'] ?? 3600),
            // one notification per conversation / task replaces the previous one instead of piling up
            'collapseId' => $payload['tag'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /** Tickets come back in the same order as the messages; "DeviceNotRegistered" means the app was uninstalled. */
    public static function forgetDeadTokens(array $messages, array $tickets): void
    {
        foreach ($tickets as $i => $t) {
            if (($t['status'] ?? '') === 'error' && ($t['details']['error'] ?? '') === 'DeviceNotRegistered' && isset($messages[$i]['to'])) {
                DeviceToken::where('push_token_hash', DeviceToken::hashFor($messages[$i]['to']))->delete();
            }
        }
    }

    private static function headers(): array
    {
        $h = ['Accept' => 'application/json', 'Accept-Encoding' => 'gzip, deflate'];
        if ($tok = config('services.expo_push.access_token')) $h['Authorization'] = 'Bearer ' . $tok;
        return $h;
    }

    /** Background curl, body piped in so message text never appears in the process list. */
    public static function detachedCommand(string $body): string
    {
        $parts = [escapeshellarg(Realtime::curlBinary() ?? 'curl'), '-sS', '-m', '15', '-X', 'POST', '-H', escapeshellarg('Content-Type: application/json'), '-H', escapeshellarg('Accept: application/json')];
        if ($tok = config('services.expo_push.access_token')) {
            $parts[] = '-H';
            $parts[] = escapeshellarg('Authorization: Bearer ' . $tok);
        }
        $parts[] = '--data-binary';
        $parts[] = '@-';
        $parts[] = escapeshellarg(self::ENDPOINT);
        return '{ printf %s ' . escapeshellarg(base64_encode($body)) . ' | base64 -d | ' . implode(' ', $parts) . ' > /dev/null 2>&1; } &';
    }
}
