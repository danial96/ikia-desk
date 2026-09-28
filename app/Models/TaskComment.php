<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'task_id', 'user_id', 'content', 'mentions',
        'bitrix_id', 'is_system', 'files', 'parent_id', 'edited_at', 'reactions',
    ];

    protected $casts = [
        'mentions'  => 'array',
        'files'     => 'array',
        'is_system' => 'boolean',
        'edited_at' => 'datetime',
        'reactions' => 'array',
    ];

    public function task() { return $this->belongsTo(Task::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function parent() { return $this->belongsTo(TaskComment::class, 'parent_id'); }
}
