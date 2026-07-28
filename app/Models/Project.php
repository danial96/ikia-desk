<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'created_by', 'status', 'color',
        'bitrix_id', 'type', 'image', 'owner_id', 'site_id',
        'number_of_members', 'bitrix_date_create', 'bitrix_date_update',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function tasks() { return $this->hasMany(Task::class); }

    public function getTaskCountAttribute(): int
    {
        return $this->tasks()->count();
    }

    public function getCompletedTaskCountAttribute(): int
    {
        return $this->tasks()->where('status', 'completed')->count();
    }
}
