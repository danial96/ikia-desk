<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    /**
     * Longest message / task comment we accept, in characters. The column is TEXT (65,535 bytes) and an
     * emoji is 4 bytes, so 15,000 characters can never overflow it even in the worst case. (It used to be
     * 5,000, which silently ate anything longer — a long paste just vanished.)
     */
    public const MAX_CHARS = 15000;

    protected $fillable = ['conversation_id', 'parent_id', 'user_id', 'content', 'mentions', 'attachment', 'edited_at', 'deleted_for', 'reactions', 'bitrix_id', 'created_at', 'updated_at'];
    protected $casts = ['mentions' => 'array', 'deleted_for' => 'array', 'reactions' => 'array', 'edited_at' => 'datetime'];

    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function parent() { return $this->belongsTo(Message::class, 'parent_id'); }
}
