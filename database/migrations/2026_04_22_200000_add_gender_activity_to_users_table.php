<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 16)->nullable()->after('height_cm');
            $table->string('activity_level', 16)->nullable()->after('gender');
        });

        // Старый онбординг: после роста сразу цель — вставим шаги пол/активность.
        DB::table('users')
            ->whereNull('gender')
            ->whereNotNull('height_cm')
            ->where('onboarding_step', 'ask_goal')
            ->update(['onboarding_step' => 'ask_gender']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'activity_level']);
        });
    }
};
