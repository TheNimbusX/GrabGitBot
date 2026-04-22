<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('onboarding_step')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('goal', 32)->nullable();
            $table->string('experience', 32)->nullable();
            $table->decimal('sleep_target_hours', 4, 2)->nullable();

            $table->unsignedInteger('daily_calories_target')->nullable();
            $table->unsignedSmallInteger('protein_g')->nullable();
            $table->unsignedSmallInteger('fat_g')->nullable();
            $table->unsignedSmallInteger('carbs_g')->nullable();
            $table->unsignedSmallInteger('water_goal_ml')->nullable();

            $table->string('before_photo_file_id')->nullable();
            $table->timestamp('next_progress_photo_at')->nullable();

            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
