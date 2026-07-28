<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'avatar',
        'phone', 'department', 'is_active', 'permissions',
        // Bitrix migration fields
        'bitrix_id', 'first_name', 'last_name', 'position',
        'work_email', 'work_phone', 'personal_phone', 'skype',
        'gender', 'birthday', 'hired_date', 'time_zone',
        'last_login_at', 'bitrix_photo_url', 'last_seen_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at'      => 'datetime',
            'password'  => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isAdmin(): bool { return in_array($this->role, ['super_admin', 'admin']); }

    public function departments() { return $this->belongsToMany(Department::class, 'department_user'); }
    public function projects() { return $this->hasMany(Project::class, 'created_by'); }
    public function tasks() { return $this->hasMany(Task::class, 'assigned_to'); }
    public function createdTasks() { return $this->hasMany(Task::class, 'created_by'); }
    public function taskMemberships() { return $this->hasMany(TaskMember::class); }
    public function conversations() { return $this->belongsToMany(Conversation::class, 'conversation_members')->withPivot('last_read_at'); }
    public function messages() { return $this->hasMany(Message::class); }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        $initial = strtoupper(substr($this->name, 0, 1));
        return "https://ui-avatars.com/api/?name={$this->name}&background=6366f1&color=fff&size=128";
    }
}
