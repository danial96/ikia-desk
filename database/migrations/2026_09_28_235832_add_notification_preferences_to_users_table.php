<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_messages')->default(true)->after('theme_image');
            $table->boolean('notify_messages_sound')->default(true)->after('notify_messages');
            $table->boolean('notify_tasks')->default(true)->after('notify_messages_sound');
            $table->boolean('notify_tasks_sound')->default(true)->after('notify_tasks');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_messages', 'notify_messages_sound', 'notify_tasks', 'notify_tasks_sound']);
        });
    }
};
