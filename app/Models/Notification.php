<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'actor_id', 'type', 'task_id', 'task_title', 'message', 'read_at',
    ];

    protected $casts = ['read_at' => 'datetime'];

    public function user()  { return $this->belongsTo(User::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function task()  { return $this->belongsTo(Task::class); }

    public function isUnread(): bool { return is_null($this->read_at); }

    /**
     * Create notifications for a list of user IDs.
     * Skips the actor (you don't notify yourself).
     */
    public static function notify(array $userIds, User $actor, string $type, Task $task, string $message): void
    {
        foreach (array_unique($userIds) as $uid) {
            if ((int)$uid === (int)$actor->id) continue;
            static::create([
                'user_id'    => $uid,
                'actor_id'   => $actor->id,
                'type'       => $type,
                'task_id'    => $task->id,
                'task_title' => $task->title,
                'message'    => $message,
            ]);
        }
    }
}
