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
            $table->string('plan_mode', 16)->nullable()->after('onboarding_step');
        });

        DB::table('users')
            ->whereNotNull('daily_calories_target')
            ->whereNull('plan_mode')
            ->update(['plan_mode' => 'full']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('plan_mode');
        });
    }
};
