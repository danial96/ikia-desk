<?php

namespace App\Support;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Browser push notifications (Web Push: RFC 8030 + VAPID RFC 8292 + payload encryption RFC 8291),
 * so a message / task alert reaches people even when Desk is closed.
 *
 * Pure PHP on ext-openssl: production has no composer, so no web-push package can be installed.
 * Off (every method a no-op) until WEBPUSH_PUBLIC_KEY / WEBPUSH_PRIVATE_KEY exist in .env.
 */
class WebPush
{
    public static function enabled(): bool
    {
        $c = config('services.webpush');
        return !empty($c['public_key']) && !empty($c['private_key']);
    }

    public static function publicKey(): ?string
    {
        return self::enabled() ? config('services.webpush.public_key') : null;
    }

    /** A fresh VAPID key pair as [publicKey, privateKey], both unpadded base64url (public = 65-byte point, private = 32-byte scalar). */
    public static function generateKeys(): array
    {
        $key = self::newEcKey();
        $d   = openssl_pkey_get_details($key)['ec'];
        return [
            self::b64u("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT)),
            self::b64u(str_pad($d['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    /**
     * Push $payload (title/body/url/tag/...) to every device of the given users. Never throws.
     * Devices are looked up here; the actual POSTs go out from a detached background process
     * (see Realtime::detachedAvailable) so a message send never waits on Google's / Mozilla's servers.
     */
    public static function sendToUsers(array $userIds, array $payload): void
    {
        if (!self::enabled()) return;
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) return;

        try {
            $subs = PushSubscription::whereIn('user_id', $userIds)->get();
            if ($subs->isEmpty()) return;

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $reqs = [];
            foreach ($subs as $sub) {
                $reqs[] = ['sub' => $sub, 'req' => self::buildRequest($sub->endpoint, $sub->p256dh, $sub->auth, $json, (string) ($payload['urgency'] ?? 'high'), null, (int) ($payload['ttl'] ?? 3600))];
            }

            if (Realtime::detachedAvailable()) {
                foreach (array_chunk($reqs, 8) as $chunk) {     // keeps each shell command well under the kernel's per-argument limit
                    $cmds = array_map(fn ($r) => self::detachedCommand($r['req']), $chunk);
                    @exec('( ' . implode(' ', $cmds) . ' wait ) > /dev/null 2>&1 &');
                }
                return;
            }

            foreach ($reqs as $r) {
                $res = Http::timeout(5)->withHeaders($r['req']['headers'])
                    ->withBody($r['req']['body'], 'application/octet-stream')->post($r['req']['url']);
                if (in_array($res->status(), [404, 410], true)) $r['sub']->delete();          // browser unsubscribed / expired
                elseif (!$res->successful()) Log::warning('WebPush rejected', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 200)]);
            }
        } catch (\Throwable $e) {
            Log::warning('WebPush failed: ' . $e->getMessage());
        }
    }

    /** What a "new chat message" push says. Text only ever travels encrypted (RFC 8291) to the recipient's browser. */
    public static function chatPayload($conv, $sender, string $content, string $url): array
    {
        $isDirect = $conv->type === 'direct';
        $label    = $conv->type === 'general' ? 'General Chat' : ($conv->name ?: 'Group chat');
        $snippet  = self::snippet($content);

        return [
            'title' => $isDirect ? $sender->name : $label,
            'body'  => $isDirect ? $snippet : $sender->name . ': ' . $snippet,
            'url'   => $url,
            'tag'   => 'chat-' . $conv->id,
            'type'  => 'message',
        ];
    }

    /** One-line plain preview: attachments/voice notes become a word, whitespace collapses, capped at 140 chars. */
    public static function snippet(?string $text): string
    {
        $t = preg_replace(
            ['/\[img\].*?\[\/img\]/s', '/\[file name="[^"]*"\].*?\[\/file\]/s', '/\[voice[^\]]*\].*?\[\/voice\]/s'],
            ['📷 Photo', '📎 File', '🎤 Voice message'],
            (string) $text
        );
        $t = trim(preg_replace('/\s+/u', ' ', $t));
        return mb_strlen($t) > 140 ? mb_substr($t, 0, 139) . '…' : ($t === '' ? 'New message' : $t);
    }

    /** The shell snippet for one detached delivery: body piped in (it is binary), so nothing needs a temp file. */
    public static function detachedCommand(array $req): string
    {
        $parts = [escapeshellarg(Realtime::curlBinary() ?? 'curl'), '-sS', '-m', '15', '-X', 'POST'];
        foreach ($req['headers'] as $name => $value) {
            $parts[] = '-H';
            $parts[] = escapeshellarg($name . ': ' . $value);
        }
        $parts[] = '--data-binary';
        $parts[] = '@-';
        $parts[] = escapeshellarg($req['url']);
        return '{ printf %s ' . escapeshellarg(base64_encode($req['body'])) . ' | base64 -d | ' . implode(' ', $parts) . ' > /dev/null 2>&1; } &';
    }

    /** Everything needed to POST one encrypted, VAPID-signed notification to a push service. */
    public static function buildRequest(string $endpoint, string $p256dh, string $auth, string $payloadJson, string $urgency = 'high', ?array $fixed = null, int $ttl = 3600): array
    {
        $parts = parse_url($endpoint);
        $aud   = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return [
            'url'     => $endpoint,
            'body'    => self::encrypt($payloadJson, self::b64uDecode($p256dh), self::b64uDecode($auth), $fixed),
            'headers' => [
                'Authorization'    => 'vapid t=' . self::vapidJwt($aud) . ', k=' . config('services.webpush.public_key'),
                'Content-Encoding' => 'aes128gcm',
                'Content-Type'     => 'application/octet-stream',
                'TTL'              => (string) max(0, $ttl),
                'Urgency'          => in_array($urgency, ['very-low', 'low', 'normal', 'high'], true) ? $urgency : 'high',
            ],
        ];
    }

    /**
     * RFC 8291 "aes128gcm" payload encryption. $fixed = ['private' => raw 32 bytes, 'public' => raw 65 bytes, 'salt' => 16 bytes]
     * pins the otherwise random sender key / salt (only the RFC test vector does that).
     */
    public static function encrypt(string $plaintext, string $uaPublic, string $authSecret, ?array $fixed = null): string
    {
        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04") throw new \InvalidArgumentException('Bad subscription public key');
        if (strlen($plaintext) > 3800) throw new \InvalidArgumentException('Push payload too large');

        if ($fixed) {
            $asPublic = $fixed['public'];
            $asKey    = openssl_pkey_get_private(self::ecPrivatePem($fixed['private'], $asPublic));
            $salt     = $fixed['salt'];
        } else {
            $asKey = self::newEcKey();
            $d     = openssl_pkey_get_details($asKey)['ec'];
            $asPublic = "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
            $salt  = random_bytes(16);
        }

        $secret = openssl_pkey_derive(openssl_pkey_get_public(self::ecPublicPem($uaPublic)), $asKey, 32);
        if ($secret === false) throw new \RuntimeException('ECDH failed');

        $ikm   = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $tag    = '';
        $cipher = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) throw new \RuntimeException('Encryption failed');

        return $salt . pack('N', 4096) . chr(strlen($asPublic)) . $asPublic . $cipher . $tag;
    }

    /** Inverse of encrypt(), only used by the tests (proves the body is something a browser could open). */
    public static function decrypt(string $body, string $uaPrivate, string $uaPublic, string $authSecret): string
    {
        $salt  = substr($body, 0, 16);
        $idLen = ord($body[20]);
        $asPublic = substr($body, 21, $idLen);
        $rest  = substr($body, 21 + $idLen);

        $secret = openssl_pkey_derive(openssl_pkey_get_public(self::ecPublicPem($asPublic)), openssl_pkey_get_private(self::ecPrivatePem($uaPrivate, $uaPublic)), 32);
        $ikm    = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $cek    = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce  = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $plain = openssl_decrypt(substr($rest, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($rest, -16));
        return $plain === false ? '' : substr($plain, 0, strrpos($plain, "\x02"));
    }

    /** RFC 8292 JWT, ES256-signed with the VAPID private key; valid 12 h. */
    public static function vapidJwt(string $audience): string
    {
        $pub = self::b64uDecode(config('services.webpush.public_key'));
        $key = openssl_pkey_get_private(self::ecPrivatePem(self::b64uDecode(config('services.webpush.private_key')), $pub));

        $signing = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            . '.' . self::b64u(json_encode(['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => config('services.webpush.subject')]));

        openssl_sign($signing, $der, $key, OPENSSL_ALGO_SHA256);
        return $signing . '.' . self::b64u(self::derToRawSignature($der));
    }

    /** OpenSSL gives an ASN.1 SEQUENCE{INTEGER r, INTEGER s}; JWT wants r||s as two fixed 32-byte numbers. */
    public static function derToRawSignature(string $der): string
    {
        $pos = 2 + ((ord($der[1]) & 0x80) ? (ord($der[1]) & 0x7f) : 0);        // skip SEQUENCE header
        $ints = [];
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $ints[] = str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }
        return $ints[0] . $ints[1];
    }

    /** A new P-256 key. Some Windows PHP builds (XAMPP) ship without openssl.cnf and refuse to generate keys; a minimal config file is enough there. */
    private static function newEcKey()
    {
        $opts = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        $key  = @openssl_pkey_new($opts);
        if ($key === false) {
            $cnf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ikia-openssl.cnf';
            if (!is_file($cnf)) file_put_contents($cnf, "openssl_conf = openssl_init
[openssl_init]
[req]
default_bits = 2048
distinguished_name = dn
[dn]
");
            $key = openssl_pkey_new($opts + ['config' => $cnf]);
        }
        if ($key === false) throw new \RuntimeException('OpenSSL could not generate an EC key: ' . openssl_error_string());
        return $key;
    }

    private static function ecPrivatePem(string $d, string $public): string
    {
        // ECPrivateKey { version 1, privateKey, [0] prime256v1, [1] publicKey }
        $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $public;
        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    private static function ecPublicPem(string $point): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function b64u(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
