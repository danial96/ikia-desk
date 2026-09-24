<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitrix task attachments (UF_TASK_WEBDAV_FILES) are *attached-object* IDs, a different
     * ID space from disk file/object IDs (bitrix_file_id). The old importer mixed them up and
     * stored an unrelated file. Keep the attached-object ID separately so a corrected row can be
     * told apart from a legacy one and so re-imports stay idempotent.
     */
    public function up(): void
    {
        Schema::table('task_files', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_attached_id')->nullable()->after('bitrix_file_id');
            $table->index('bitrix_attached_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_files', function (Blueprint $table) {
            $table->dropIndex(['bitrix_attached_id']);
            $table->dropColumn('bitrix_attached_id');
        });
    }
};
