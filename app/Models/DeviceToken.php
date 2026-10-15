<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'api_token_id', 'push_token_hash', 'push_token', 'platform', 'app_version', 'last_used_at'];

    protected $casts = ['last_used_at' => 'datetime'];

    public static function hashFor(string $pushToken): string
    {
        return hash('sha256', $pushToken);
    }

    public function user() { return $this->belongsTo(User::class); }
}
