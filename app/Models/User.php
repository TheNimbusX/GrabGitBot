<?php

namespace App\Models;

use App\Enums\UserPlanMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
        'plan_mode',
        'weight_kg',
        'starting_weight_kg',
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
        'weekly_focus_note',
        'notify_morning',
        'notify_evening',
        'notify_churn',
        'notify_quiet_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'notify_weekly_focus_reminder',
        'notify_weekly_weight_reminder',
        'last_message_to_bot_at',
        'recovery_mode_until',
        'recovery_mode_started_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'starting_weight_kg' => 'float',
        'age' => 'integer',
        'height_cm' => 'integer',
        'sleep_target_hours' => 'float',
        'daily_calories_target' => 'integer',
        'protein_g' => 'integer',
        'fat_g' => 'integer',
        'carbs_g' => 'integer',
        'water_goal_ml' => 'integer',
        'next_progress_photo_at' => 'datetime',
        'last_message_to_bot_at' => 'datetime',
        'recovery_mode_until' => 'datetime',
        'recovery_mode_started_at' => 'datetime',
        'notify_morning' => 'boolean',
        'notify_evening' => 'boolean',
        'notify_churn' => 'boolean',
        'notify_quiet_enabled' => 'boolean',
        'notify_weekly_focus_reminder' => 'boolean',
        'notify_weekly_weight_reminder' => 'boolean',
    ];

    public function dailyChecks(): HasMany
    {
        return $this->hasMany(DailyCheck::class);
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(UserWeightLog::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(UserSupportMessage::class);
    }

    public function hasCompletedOnboarding(): bool
    {
        if ($this->onboarding_step !== null && $this->onboarding_step !== '') {
            return false;
        }

        if ($this->isDisciplineOnlyMode()) {
            return $this->water_goal_ml !== null && $this->sleep_target_hours !== null;
        }

        return $this->daily_calories_target !== null;
    }

    public function isDisciplineOnlyMode(): bool
    {
        return $this->plan_mode === UserPlanMode::Discipline->value;
    }

    /** Полный план бота или старые пользователи без plan_mode, но с калориями. */
    public function usesGeneratedNutritionPlan(): bool
    {
        if ($this->isDisciplineOnlyMode()) {
            return false;
        }

        return $this->daily_calories_target !== null;
    }

    /** Пользователи с завершённым онбордингом (чек-ины / рассылки). */
    public function scopeCompletedFitbotOnboarding(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('onboarding_step')->orWhere('onboarding_step', '');
        })->where(function ($q) {
            $q->whereNotNull('daily_calories_target')
                ->orWhere(function ($q2) {
                    $q2->where('plan_mode', UserPlanMode::Discipline->value)
                        ->whereNotNull('water_goal_ml')
                        ->whereNotNull('sleep_target_hours');
                });
        });
    }

    public function onboardingStepEnum(): ?\App\Enums\OnboardingStep
    {
        if ($this->onboarding_step === null || $this->onboarding_step === '') {
            return null;
        }

        return \App\Enums\OnboardingStep::tryFrom($this->onboarding_step);
    }

    /** Утро / вечер / churn — учитываются флаги и тихие часы (локаль приложения). */
    public function allowsBotPushAt(Carbon $at, string $kind): bool
    {
        if ($this->isRecoveryModeActive($at)) {
            return false;
        }

        $on = match ($kind) {
            'morning' => (bool) ($this->notify_morning ?? true),
            'evening' => (bool) ($this->notify_evening ?? true),
            'churn' => (bool) ($this->notify_churn ?? true),
            'weekly_focus' => (bool) ($this->notify_weekly_focus_reminder ?? true),
            'weekly_weight' => (bool) ($this->notify_weekly_weight_reminder ?? true),
            default => true,
        };
        if (! $on) {
            return false;
        }

        return ! $this->isInQuietHours($at);
    }

    public function isRecoveryModeActive(?Carbon $at = null): bool
    {
        $at ??= Carbon::now();

        return $this->recovery_mode_until !== null && $this->recovery_mode_until->greaterThan($at);
    }

    public function isInQuietHours(Carbon $at): bool
    {
        if (! (bool) ($this->notify_quiet_enabled ?? true)) {
            return false;
        }
        $start = (string) ($this->quiet_hours_start ?? '22:00');
        $end = (string) ($this->quiet_hours_end ?? '08:00');
        if ($start === $end) {
            return false;
        }
        $t = $at->format('H:i');
        if ($start < $end) {
            return $t >= $start && $t < $end;
        }

        return $t >= $start || $t < $end;
    }
}
