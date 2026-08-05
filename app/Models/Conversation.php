<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['type', 'name', 'avatar', 'created_by', 'bitrix_chat_id'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function members() { return $this->belongsToMany(User::class, 'conversation_members')->withPivot('last_read_at'); }
    public function messages() { return $this->hasMany(Message::class)->orderBy('created_at'); }
    public function lastMessage() { return $this->hasOne(Message::class)->latestOfMany(); }

    public function getUnreadCountForUser(User $user): int
    {
        $member = $this->members()->where('user_id', $user->id)->first();
        if (!$member || !$member->pivot->last_read_at) {
            return $this->messages()->count();
        }
        return $this->messages()->where('created_at', '>', $member->pivot->last_read_at)->count();
    }
}
