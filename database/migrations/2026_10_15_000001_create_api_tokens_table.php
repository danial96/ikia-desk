<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per signed-in phone. The token itself is shown to the app once; only its sha256 is kept.
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('device_name', 120)->nullable();
            $table->string('platform', 16)->nullable();         // ios | android
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // Phone push (FCM) registration tokens, one per installed app. Sending is a later phase; this is where they land.
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_token_id')->nullable()->constrained('api_tokens')->cascadeOnDelete();
            $table->string('push_token_hash', 64)->unique();    // sha256 of the push token (they are long, so index the hash)
            $table->text('push_token');
            $table->string('platform', 16);
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('api_tokens');
    }
};
