<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('bitrix_chat_id')->nullable()->unique()->after('id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_id')->nullable()->unique()->after('id');
            $table->timestamp('edited_at')->nullable()->after('attachment');
        });
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
