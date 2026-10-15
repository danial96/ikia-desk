<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'device_name', 'platform', 'app_version', 'last_used_at'];

    protected $casts = ['last_used_at' => 'datetime'];

    public static function hashFor(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Creates a token for the user. Returns [row, plain text token] — the plain text exists only in this return value. */
    public static function issue(User $user, ?string $deviceName = null, ?string $platform = null, ?string $appVersion = null): array
    {
        $plain = Str::random(64);
        $row = static::create([
            'user_id'      => $user->id,
            'token_hash'   => static::hashFor($plain),
            'device_name'  => $deviceName ? mb_substr($deviceName, 0, 120) : null,
            'platform'     => $platform,
            'app_version'  => $appVersion ? mb_substr($appVersion, 0, 32) : null,
            'last_used_at' => now(),
        ]);
        return [$row, $plain];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function devices() { return $this->hasMany(DeviceToken::class); }
}
