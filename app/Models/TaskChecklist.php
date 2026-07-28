<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskChecklist extends Model
{
    protected $fillable = ['task_id','added_by','text','is_complete','completed_by','completed_at','sort_index'];

    protected $casts = ['is_complete' => 'boolean', 'completed_at' => 'datetime'];

    public function task()   { return $this->belongsTo(Task::class); }
    public function addedBy(){ return $this->belongsTo(User::class, 'added_by'); }
}
