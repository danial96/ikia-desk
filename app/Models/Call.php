<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = ['caller_id', 'callee_id', 'conversation_id', 'status', 'answered_at', 'ended_at', 'duration'];

    protected $casts = ['answered_at' => 'datetime', 'ended_at' => 'datetime'];

    /** How long a call may ring, and how long an answered call may go without a heartbeat before we treat it as dropped. */
    public const RING_SECONDS      = 60;
    public const HEARTBEAT_SECONDS = 90;

    public function caller() { return $this->belongsTo(User::class, 'caller_id'); }
    public function callee() { return $this->belongsTo(User::class, 'callee_id'); }

    public function isLive(): bool { return in_array($this->status, ['ringing', 'active'], true); }

    public function involves(int $userId): bool
    {
        return (int) $this->caller_id === $userId || (int) $this->callee_id === $userId;
    }

    public function otherId(int $userId): int
    {
        return (int) $this->caller_id === $userId ? (int) $this->callee_id : (int) $this->caller_id;
    }

    /** Calls that are still "live" in the table but whose browsers vanished (closed laptop, lost network). */
    public function scopeStale($q)
    {
        return $q->where(function ($q) {
            $q->where(fn ($r) => $r->where('status', 'ringing')->where('created_at', '<', now()->subSeconds(self::RING_SECONDS)))
              ->orWhere(fn ($a) => $a->where('status', 'active')->where('updated_at', '<', now()->subSeconds(self::HEARTBEAT_SECONDS)));
        });
    }

    public function scopeLive($q)
    {
        return $q->whereIn('status', ['ringing', 'active']);
    }
}
