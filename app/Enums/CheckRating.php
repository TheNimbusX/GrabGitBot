<?php

namespace App\Enums;

enum CheckRating: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    public function emoji(): string
    {
        return match ($this) {
            self::Green => '🟢',
            self::Yellow => '🟡',
            self::Red => '🔴',
        };
    }

    public function labelRu(): string
    {
        return match ($this) {
            self::Green => 'идеально',
            self::Yellow => 'нормально',
            self::Red => 'плохо',
        };
    }

    public function points(): int
    {
        return match ($this) {
            self::Green => 2,
            self::Yellow => 1,
            self::Red => 0,
        };
    }
}
