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
        'sleep_rating',
        'workout_rating',
        'water_rating',
        'total_score',
        'is_completed',
    ];

    protected $casts = [
        'check_date' => 'date',
        'is_completed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
