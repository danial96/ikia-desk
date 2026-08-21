<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'title', 'description', 'created_by',
        'assigned_to', 'status', 'priority', 'deadline',
        // Bitrix migration fields
        'bitrix_id', 'bitrix_group_id', 'chat_id',
        'start_date', 'closed_date',
        'time_estimate', 'time_spent',
        'allow_change_deadline', 'task_control',
        'stage_id', 'sort_index',
    ];

    protected $casts = [
        'deadline'              => 'datetime',
        'start_date'            => 'datetime',
        'closed_date'           => 'datetime',
        'allow_change_deadline' => 'boolean',
        'task_control'          => 'boolean',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function members() { return $this->belongsToMany(User::class, 'task_members'); }
    public function observers() { return $this->belongsToMany(User::class, 'task_observers'); }
    public function comments() { return $this->hasMany(TaskComment::class)->orderBy('created_at'); }
    public function activities() { return $this->hasMany(TaskActivity::class)->orderBy('created_at'); }
    public function checklists() { return $this->hasMany(TaskChecklist::class)->orderBy('sort_index'); }
    public function files()      { return $this->hasMany(TaskFile::class)->orderBy('created_at'); }
    public function coverFile()  { return $this->hasMany(TaskFile::class)->where('is_task_attachment', true)->orderBy('created_at'); }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists()
            || $this->created_by === $user->id
            || $this->assigned_to === $user->id;
    }

    public function logActivity(User $user, string $action, string $field = null, $oldValue = null, $newValue = null): void
    {
        $this->activities()->create([
            'user_id'   => $user->id,
            'action'    => $action,
            'field'     => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'new'         => 'bg-gray-100 text-gray-700',
            'in_progress' => 'bg-blue-100 text-blue-700',
            'paused'      => 'bg-yellow-100 text-yellow-700',
            'completed'   => 'bg-green-100 text-green-700',
            default       => 'bg-gray-100 text-gray-700',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low'    => 'bg-gray-100 text-gray-600',
            'medium' => 'bg-blue-100 text-blue-600',
            'high'   => 'bg-orange-100 text-orange-600',
            'urgent' => 'bg-red-100 text-red-600',
            default  => 'bg-gray-100 text-gray-600',
        };
    }
}
