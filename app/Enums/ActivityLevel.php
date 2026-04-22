<?php

namespace App\Enums;

enum ActivityLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function labelRu(): string
    {
        return match ($this) {
            self::Low => 'Низкая',
            self::Medium => 'Средняя',
            self::High => 'Высокая',
        };
    }

    public function descriptionRu(): string
    {
        return match ($this) {
            self::Low => 'меньше 5k шагов/день и ~2 тренировки в неделю',
            self::Medium => '5–10k шагов/день и ~3 тренировки в неделю',
            self::High => '10k+ шагов/день и 4+ тренировок в неделю',
        };
    }
}
