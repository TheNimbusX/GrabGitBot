<?php

namespace App\Enums;

enum FitnessGoal: string
{
    case Bulk = 'bulk';
    case Cut = 'cut';
    case Maintain = 'maintain';

    public function labelRu(): string
    {
        return match ($this) {
            self::Bulk => 'набор массы',
            self::Cut => 'похудение',
            self::Maintain => 'поддержание',
        };
    }
}
