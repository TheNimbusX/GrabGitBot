<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCheck extends Model
{
    protected $fillable = [
        'user_id',
        'check_date',
        'diet_rating',
        'sleep_hours_actual',
        'sleep_rating',
        'workout_rating',
        'workout_variant',
        'water_rating',
        'water_ml_actual',
        'total_score',
        'is_completed',
        'telegram_progress_message_id',
    ];

    protected $casts = [
        'check_date' => 'date',
        'is_completed' => 'boolean',
        'sleep_hours_actual' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
