<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_checks', function (Blueprint $table) {
            $table->decimal('sleep_hours_actual', 4, 2)->nullable()->after('sleep_rating');
            $table->unsignedInteger('water_ml_actual')->nullable()->after('water_rating');
            $table->string('workout_variant', 16)->nullable()->after('workout_rating');
        });
    }

    public function down(): void
    {
        Schema::table('daily_checks', function (Blueprint $table) {
            $table->dropColumn(['sleep_hours_actual', 'water_ml_actual', 'workout_variant']);
        });
    }
};
