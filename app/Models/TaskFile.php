<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskFile extends Model
{
    protected $fillable = [
        'task_id', 'uploaded_by', 'bitrix_file_id', 'is_task_attachment',
        'name', 'size', 'mime_type', 'disk_path', 'bitrix_download_url',
    ];

    public function task()     { return $this->belongsTo(Task::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getDownloadUrlAttribute(): string
    {
        if ($this->disk_path) {
            return asset($this->disk_path);
        }
        return $this->bitrix_download_url ?? '#';
    }

    public function getIsImageAttribute(): bool
    {
        $ext = strtolower(pathinfo($this->name ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp']);
    }
}
