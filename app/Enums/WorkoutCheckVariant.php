<?php

namespace App\Enums;

enum WorkoutCheckVariant: string
{
    case Trained = 'trained';
    case Rest = 'rest';
    case Walk = 'walk';

    public function labelRu(): string
    {
        return match ($this) {
            self::Trained => 'Позанимался',
            self::Rest => 'День отдыха',
            self::Walk => 'Прогулялся',
        };
    }
}
