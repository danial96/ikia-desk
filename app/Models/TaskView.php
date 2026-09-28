<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskView extends Model
{
    public $timestamps = false;

    protected $fillable = ['task_id', 'user_id', 'viewed_at'];
    protected $casts    = ['viewed_at' => 'datetime'];

    public function task() { return $this->belongsTo(Task::class); }
    public function user() { return $this->belongsTo(User::class); }
}
