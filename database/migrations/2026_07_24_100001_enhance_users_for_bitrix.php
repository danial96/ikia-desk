<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_id')->nullable()->unique()->after('id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('position')->nullable()->after('department');   // job title
            $table->string('work_email')->nullable()->after('position');
            $table->string('work_phone')->nullable()->after('work_email');
            $table->string('personal_phone')->nullable()->after('work_phone');
            $table->string('skype')->nullable()->after('personal_phone');
            $table->string('gender', 1)->nullable()->after('skype');       // M / F
            $table->date('birthday')->nullable()->after('gender');
            $table->date('hired_date')->nullable()->after('birthday');
            $table->string('time_zone')->nullable()->after('hired_date');
            $table->timestamp('last_login_at')->nullable()->after('time_zone');
            $table->string('bitrix_photo_url')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bitrix_id','first_name','last_name','position',
                'work_email','work_phone','personal_phone','skype',
                'gender','birthday','hired_date','time_zone',
                'last_login_at','bitrix_photo_url',
            ]);
        });
    }
};
