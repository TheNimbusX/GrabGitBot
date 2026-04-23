<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWeightLog extends Model
{
    protected $fillable = [
        'user_id',
        'weight_kg',
    ];

    protected $casts = [
        'weight_kg' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
