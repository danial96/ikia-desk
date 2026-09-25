<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** conversations.type was an ENUM(direct, group, general); a personal "Notes" chat needs a fourth value. */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type', 20)->default('direct')->change();
        });
    }

    public function down(): void
    {
        // left as string: shrinking back to the enum would fail once notes rows exist
    }
};
