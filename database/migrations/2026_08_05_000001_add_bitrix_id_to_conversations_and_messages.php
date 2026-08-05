<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('bitrix_chat_id')->nullable()->unique()->after('id');
            });
        } catch (\Exception $e) { /* column already exists */ }

        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->unsignedBigInteger('bitrix_id')->nullable()->unique()->after('id');
            });
        } catch (\Exception $e) { /* column already exists */ }

        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->timestamp('edited_at')->nullable()->after('attachment');
            });
        } catch (\Exception $e) { /* column already exists */ }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('bitrix_chat_id');
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['bitrix_id', 'edited_at']);
        });
    }
};
