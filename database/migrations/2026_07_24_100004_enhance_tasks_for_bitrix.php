<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_id')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('bitrix_group_id')->nullable()->after('bitrix_id');   // workgroup ID
            $table->unsignedBigInteger('chat_id')->nullable()->after('bitrix_group_id');      // Bitrix IM chat ID
            $table->timestamp('start_date')->nullable()->after('deadline');
            $table->timestamp('closed_date')->nullable()->after('start_date');
            $table->unsignedInteger('time_estimate')->default(0)->after('closed_date');       // seconds
            $table->unsignedInteger('time_spent')->default(0)->after('time_estimate');        // seconds
            $table->boolean('allow_change_deadline')->default(false)->after('time_spent');
            $table->boolean('task_control')->default(false)->after('allow_change_deadline');  // needs creator approval
            $table->string('stage_id')->nullable()->after('task_control');
            $table->integer('sort_index')->default(0)->after('stage_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'bitrix_id','bitrix_group_id','chat_id',
                'start_date','closed_date','time_estimate','time_spent',
                'allow_change_deadline','task_control','stage_id','sort_index',
            ]);
        });
    }
};
