<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['bitrix_id', 'name', 'parent_id', 'head_id', 'sort_index'];

    public function parent()   { return $this->belongsTo(Department::class, 'parent_id'); }
    public function children() { return $this->hasMany(Department::class, 'parent_id'); }
    public function head()     { return $this->belongsTo(User::class, 'head_id'); }
    public function users()    { return $this->belongsToMany(User::class, 'department_user'); }
}
