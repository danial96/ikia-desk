<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = ['conversation_id', 'user_id', 'content', 'mentions', 'attachment', 'edited_at', 'deleted_for', 'reactions', 'bitrix_id', 'created_at', 'updated_at'];
    protected $casts = ['mentions' => 'array', 'deleted_for' => 'array', 'reactions' => 'array', 'edited_at' => 'datetime'];

    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function user() { return $this->belongsTo(User::class); }
}
