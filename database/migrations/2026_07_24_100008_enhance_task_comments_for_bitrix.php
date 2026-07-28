<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_id')->nullable()->unique()->after('id');
            $table->boolean('is_system')->default(false)->after('content');  // system-generated comment
            $table->json('files')->nullable()->after('is_system');           // attached file IDs
            $table->unsignedBigInteger('parent_id')->nullable()->after('files'); // reply-to comment
            $table->timestamp('edited_at')->nullable()->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropColumn(['bitrix_id','is_system','files','parent_id','edited_at']);
        });
    }
};
