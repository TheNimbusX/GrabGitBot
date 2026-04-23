<?php

namespace App\Enums;

/**
 * Геймификация: уровень по текущей серии закрытых чек-инов подряд (как в RatingService::checkInStreakDays).
 */
enum StrikeStatusTier: string
{
    case Novice = 'novice';
    case Snowdrop = 'snowdrop';
    case Amateur = 'amateur';
    case Experienced = 'experienced';
    case Boss = 'boss';

    public function emoji(): string
    {
        return match ($this) {
            self::Novice => '🌱',
            self::Snowdrop => '❄️',
            self::Amateur => '💪',
            self::Experienced => '🎯',
            self::Boss => '👑',
        };
    }

    public function labelRu(): string
    {
        return match ($this) {
            self::Novice => 'Новичок',
            self::Snowdrop => 'Подснежник',
            self::Amateur => 'Любитель',
            self::Experienced => 'Опытный',
            self::Boss => 'Босс качалки',
        };
    }

    /** Пояснение одной строкой (для профиля и справки). */
    public function criteriaRu(): string
    {
        return match ($this) {
            self::Novice => 'серия 0–7 дней',
            self::Snowdrop => 'серия 8–14 дней',
            self::Amateur => 'серия 15–30 дней',
            self::Experienced => 'серия 31–60 дней',
            self::Boss => 'серия от 61 дня',
        };
    }

    public static function fromCheckInStreak(int $streak): self
    {
        return match (true) {
            $streak <= 7 => self::Novice,
            $streak <= 14 => self::Snowdrop,
            $streak <= 30 => self::Amateur,
            $streak <= 60 => self::Experienced,
            default => self::Boss,
        };
    }

    /** Минимальная серия (дней) для этого уровня. */
    public function minStreakInclusive(): int
    {
        return match ($this) {
            self::Novice => 0,
            self::Snowdrop => 8,
            self::Amateur => 15,
            self::Experienced => 31,
            self::Boss => 61,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Novice => self::Snowdrop,
            self::Snowdrop => self::Amateur,
            self::Amateur => self::Experienced,
            self::Experienced => self::Boss,
            self::Boss => null,
        };
    }

    /** Сколько дней серии не хватает до следующего уровня; null — уже максимум. */
    public function daysUntilNext(int $currentStreak): ?int
    {
        $next = $this->next();
        if ($next === null) {
            return null;
        }
        $need = $next->minStreakInclusive();

        return max(0, $need - $currentStreak);
    }
}
