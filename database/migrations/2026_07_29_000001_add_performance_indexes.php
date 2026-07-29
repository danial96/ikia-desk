<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('status');
            $table->index('deadline');
            $table->index('deleted_at');
            $table->index(['assigned_to', 'status']);
            $table->index(['created_by', 'status']);
            $table->index(['project_id', 'status']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at']);
            $table->index('deleted_at');
        });

        Schema::table('task_members', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('task_observers', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['deadline']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropIndex(['created_by', 'status']);
            $table->dropIndex(['project_id', 'status']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('task_members', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('task_observers', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'read_at']);
        });
    }
};
