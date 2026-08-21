<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('task_files', function (Blueprint $table) {
            $table->boolean('is_task_attachment')->default(false)->after('bitrix_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_files', function (Blueprint $table) {
            $table->dropColumn('is_task_attachment');
        });
    }
};
