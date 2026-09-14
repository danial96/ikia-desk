<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tasks.status column was created as enum('new','in_progress','review','completed'),
 * but the application uses 'paused' (not 'review'). On MySQL that meant setting a task to
 * "paused" failed or silently blanked the status. Widen it to a string; the controllers
 * already validate the allowed values (new, in_progress, paused, completed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status', 32)->default('new')->change();
        });
    }

    public function down(): void
    {
        // Best-effort reverse: map any value outside the original enum back to a valid one first.
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['new', 'in_progress', 'review', 'completed'])->default('new')->change();
        });
    }
};
