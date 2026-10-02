<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The conversation list asks "latest message per conversation" (MAX(id) ... GROUP BY
     * conversation_id WHERE deleted_at IS NULL) on every poll. With only (conversation_id, created_at)
     * to go on MySQL had to read every message row to check deleted_at — ~260ms across 120k rows.
     * This index lets that whole subquery be answered from the index alone.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'deleted_at', 'id'], 'messages_conv_deleted_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conv_deleted_id_index');
        });
    }
};
