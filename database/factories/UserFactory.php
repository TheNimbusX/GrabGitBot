<?php

namespace Database\Factories;

use App\Enums\OnboardingStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'telegram_id' => fake()->unique()->numberBetween(100000, 999999999),
            'username' => fake()->userName(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'onboarding_step' => OnboardingStep::AskWeight->value,
            'weight_kg' => null,
            'height_cm' => null,
            'goal' => null,
            'experience' => null,
            'sleep_target_hours' => null,
            'daily_calories_target' => null,
            'protein_g' => null,
            'fat_g' => null,
            'carbs_g' => null,
            'water_goal_ml' => null,
            'before_photo_file_id' => null,
            'next_progress_photo_at' => null,
            'password' => null,
        ];
    }
}
