<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('bitrix_file_id')->nullable()->index();
            $table->string('name');
            $table->unsignedBigInteger('size')->default(0);         // bytes
            $table->string('mime_type')->nullable();
            $table->string('disk_path')->nullable();                 // local storage path after download
            $table->text('bitrix_download_url')->nullable();         // temporary Bitrix URL
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_files');
    }
};
