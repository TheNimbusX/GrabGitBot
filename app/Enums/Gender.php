<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function labelRu(): string
    {
        return match ($this) {
            self::Male => 'Мужской',
            self::Female => 'Женский',
        };
    }
}
