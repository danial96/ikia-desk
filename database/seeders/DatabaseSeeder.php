<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::create([
            'name'       => 'Super Admin',
            'email'      => 'admin@ikia.com',
            'password'   => Hash::make('admin123'),
            'role'       => 'super_admin',
            'department' => 'Management',
            'is_active'  => true,
        ]);

        $generalChat = Conversation::create([
            'type'       => 'general',
            'name'       => 'General',
            'created_by' => $superAdmin->id,
        ]);

        $generalChat->members()->attach($superAdmin->id);
    }
}
