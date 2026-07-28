<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_id')->nullable()->unique()->after('id');
            $table->string('type')->default('local')->after('color');   // 'local' | 'workgroup'
            $table->text('image')->nullable()->after('type');           // cover image URL
            $table->unsignedBigInteger('owner_id')->nullable()->after('image'); // workgroup owner user_id
            $table->string('site_id', 10)->nullable()->after('owner_id');
            $table->integer('number_of_members')->default(0)->after('site_id');
            $table->timestamp('bitrix_date_create')->nullable()->after('number_of_members');
            $table->timestamp('bitrix_date_update')->nullable()->after('bitrix_date_create');
        });

        // Project members / workgroup members
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member'); // 'owner' | 'moderator' | 'member'
            $table->timestamps();
            $table->unique(['project_id','user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['bitrix_id','type','image','owner_id','site_id','number_of_members','bitrix_date_create','bitrix_date_update']);
        });
    }
};
