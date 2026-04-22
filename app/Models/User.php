<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'age',
        'onboarding_step',
        'weight_kg',
        'height_cm',
        'gender',
        'activity_level',
        'goal',
        'experience',
        'sleep_target_hours',
        'daily_calories_target',
        'protein_g',
        'fat_g',
        'carbs_g',
        'water_goal_ml',
        'before_photo_file_id',
        'next_progress_photo_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'age' => 'integer',
        'height_cm' => 'integer',
        'sleep_target_hours' => 'float',
        'daily_calories_target' => 'integer',
        'protein_g' => 'integer',
        'fat_g' => 'integer',
        'carbs_g' => 'integer',
        'water_goal_ml' => 'integer',
        'next_progress_photo_at' => 'datetime',
    ];

    public function dailyChecks(): HasMany
    {
        return $this->hasMany(DailyCheck::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function hasCompletedOnboarding(): bool
    {
        return ($this->onboarding_step === null || $this->onboarding_step === '')
            && $this->daily_calories_target !== null;
    }

    public function onboardingStepEnum(): ?\App\Enums\OnboardingStep
    {
        if ($this->onboarding_step === null || $this->onboarding_step === '') {
            return null;
        }

        return \App\Enums\OnboardingStep::tryFrom($this->onboarding_step);
    }
}
