<?php

namespace App\Enums;

enum WorkoutCheckVariant: string
{
    case Trained = 'trained';
    case Rest = 'rest';
    case Recovery = 'recovery';
    /** Пропустил тренировку без уважительной причины — хуже двух других вариантов. */
    case Skipped = 'skipped';

    public function labelRu(): string
    {
        return match ($this) {
            self::Trained => 'Позанимался',
            self::Rest => 'День отдыха',
            self::Recovery => 'Болею / восстановление',
            self::Skipped => 'Прогулял (пропустил тренировку)',
        };
    }
}
