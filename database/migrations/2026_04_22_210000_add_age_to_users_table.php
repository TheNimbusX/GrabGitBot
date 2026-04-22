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
            $table->unsignedTinyInteger('age')->nullable()->after('last_name');
        });

        // Новый порядок: пол → возраст → вес. Кто был на весе/росте без пола — начать заново с пола.
        DB::table('users')
            ->whereNull('gender')
            ->whereNull('daily_calories_target')
            ->whereIn('onboarding_step', ['ask_weight', 'ask_height'])
            ->update([
                'onboarding_step' => 'ask_gender',
                'weight_kg' => null,
                'height_cm' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('age');
        });
    }
};
