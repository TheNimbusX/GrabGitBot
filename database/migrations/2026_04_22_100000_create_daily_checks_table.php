<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('check_date');

            $table->string('diet_rating', 16)->nullable();
            $table->string('sleep_rating', 16)->nullable();
            $table->string('workout_rating', 16)->nullable();
            $table->string('water_rating', 16)->nullable();

            $table->unsignedTinyInteger('total_score')->default(0);
            $table->boolean('is_completed')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'check_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checks');
    }
};
