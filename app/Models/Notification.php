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
        $created = [];
        foreach (array_unique($userIds) as $uid) {
            if ((int)$uid === (int)$actor->id) continue;
            $n = static::create([
                'user_id'    => $uid,
                'actor_id'   => $actor->id,
                'type'       => $type,
                'task_id'    => $task->id,
                'task_title' => $task->title,
                'message'    => $message,
            ]);
            $created[(int) $uid] = $n->id;
        }
        // Tell those browsers right now instead of waiting for their next poll...
        \App\Support\Realtime::publishToUsers(array_keys($created), 'notif');
        // ...and reach the ones who have Desk closed.
        static::pushToDevices($created, $task->title ?: 'IKIA Desk', $message, '/tasks/kanban?task=' . $task->id, true);
    }

    /**
     * Notify mentioned users. Works for chat (no task) and comments (optional task context).
     */
    public static function mention(array $userIds, User $actor, string $message, ?Task $task = null, ?string $url = null): void
    {
        $created = [];
        foreach (array_unique($userIds) as $uid) {
            if ((int)$uid === (int)$actor->id) continue;
            $n = static::create([
                'user_id'    => $uid,
                'actor_id'   => $actor->id,
                'type'       => 'mention',
                'task_id'    => $task?->id,
                'task_title' => $task?->title,
                'message'    => $message,
            ]);
            $created[(int) $uid] = $n->id;
        }
        \App\Support\Realtime::publishToUsers(array_keys($created), 'notif');
        static::pushToDevices($created, $task?->title ?: $actor->name,
            $message, $url ?: ($task ? '/tasks/kanban?task=' . $task->id : '/chat'), (bool) $task);
    }

    /**
     * Browser push for freshly created notifications, honouring each person's own switch
     * (task activity vs messages — a mention inside a chat counts as a message).
     *
     * @param array<int,int> $created user id => notification id
     */
    private static function pushToDevices(array $created, string $title, string $body, string $url, bool $isTask): void
    {
        if (!$created || !\App\Support\WebPush::enabled()) return;
        $pref = $isTask ? 'notify_tasks' : 'notify_messages';
        foreach (User::whereIn('id', array_keys($created))->where($pref, true)->pluck('id') as $uid) {
            \App\Support\WebPush::sendToUsers([$uid], [
                'title' => $title,
                'body'  => \App\Support\WebPush::snippet($body),
                'url'   => $url,
                'tag'   => 'notif-' . $created[$uid],
                'type'  => $isTask ? 'task' : 'message',
            ]);
        }
    }
}
