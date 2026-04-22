<?php

namespace App\Enums;

enum ExperienceLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';

    public function labelRu(): string
    {
        return match ($this) {
            self::Beginner => 'новичок',
            self::Intermediate => 'средний',
        };
    }
}
