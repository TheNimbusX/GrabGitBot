<?php

namespace App\Services;

use App\Enums\CheckRating;
use App\Models\DailyCheck;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RatingService
{
    public const MAX_DAILY_POINTS = 8;

    public function pointsForRating(?string $rating): int
    {
        if ($rating === null) {
            return 0;
        }

        $enum = CheckRating::tryFrom($rating);

        return $enum?->points() ?? 0;
    }

    /**
     * Оценка сна: заметно ниже цели — плохо; чуть не доспал (например 7 ч при цели 9) — нормально;
     * близко к цели или лучше — хорошо.
     */
    public function sleepRatingFromHours(float $actualHours, float $targetHours): CheckRating
    {
        $target = max(4.0, min(12.0, $targetHours));
        $actualHours = max(0.0, min(16.0, $actualHours));

        if ($actualHours < $target - 2.5 || $actualHours < 4.5) {
            return CheckRating::Red;
        }
        if ($actualHours >= $target - 1.0) {
            return CheckRating::Green;
        }

        return CheckRating::Yellow;
    }

    /** Вода: ≥90% цели — хорошо, ≥60% — нормально, иначе плохо. */
    public function waterRatingFromMl(int $actualMl, int $goalMl): CheckRating
    {
        $goalMl = max(500, $goalMl);
        $actualMl = max(0, $actualMl);
        $ratio = $actualMl / $goalMl;

        if ($ratio >= 0.90) {
            return CheckRating::Green;
        }
        if ($ratio >= 0.60) {
            return CheckRating::Yellow;
        }

        return CheckRating::Red;
    }

    public function recalculateDailyCheck(DailyCheck $check): void
    {
        if (! $check->is_completed) {
            $check->total_score = 0;

            return;
        }

        $total = $this->pointsForRating($check->diet_rating)
            + $this->pointsForRating($check->sleep_rating)
            + $this->pointsForRating($check->workout_rating)
            + $this->pointsForRating($check->water_rating);

        $check->total_score = min(self::MAX_DAILY_POINTS, $total);
    }

    public function scoreForDay(User $user, ?Carbon $date = null): int
    {
        $date ??= Carbon::today();

        $check = $user->dailyChecks()
            ->whereDate('check_date', $date)
            ->where('is_completed', true)
            ->first();

        return (int) ($check?->total_score ?? 0);
    }

    public function scoreForPeriod(User $user, Carbon $from, Carbon $to): int
    {
        return (int) $user->dailyChecks()
            ->where('is_completed', true)
            ->whereBetween('check_date', [$from->toDateString(), $to->toDateString()])
            ->sum('total_score');
    }

    /** @return array{day: int, week: int, month: int} */
    public function summary(User $user, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        return [
            'day' => $this->scoreForDay($user, $now->copy()->startOfDay()),
            'week' => $this->scoreForPeriod(
                $user,
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ),
            'month' => $this->scoreForPeriod(
                $user,
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ),
        ];
    }

    /**
     * Короткий фидбек по последним завершённым чек-инам (по умолчанию 7 дней).
     *
     * @return list<string>
     */
    public function weakAreasFeedback(User $user, int $days = 7, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $from = $now->copy()->subDays($days)->startOfDay();

        /** @var Collection<int, DailyCheck> $checks */
        $checks = $user->dailyChecks()
            ->where('is_completed', true)
            ->where('check_date', '>=', $from->toDateString())
            ->get();

        if ($checks->isEmpty()) {
            return ['📭 Пока мало данных — отметь несколько чек-инов, и я подскажу, что чаще проседает.'];
        }

        $dimensions = [
            'diet' => ['label' => 'питание', 'getter' => fn (DailyCheck $c) => $c->diet_rating],
            'sleep' => ['label' => 'сон', 'getter' => fn (DailyCheck $c) => $c->sleep_rating],
            'workout' => ['label' => 'тренировки', 'getter' => fn (DailyCheck $c) => $c->workout_rating],
            'water' => ['label' => 'вода', 'getter' => fn (DailyCheck $c) => $c->water_rating],
        ];

        $avg = [];
        foreach ($dimensions as $key => $meta) {
            $sum = 0;
            $n = 0;
            foreach ($checks as $c) {
                $r = $meta['getter']($c);
                if ($r !== null) {
                    $sum += $this->pointsForRating($r);
                    $n++;
                }
            }
            $avg[$key] = $n > 0 ? $sum / $n : 0.0;
        }

        asort($avg);
        $lowestKey = array_key_first($avg);
        $lowestAvg = $avg[$lowestKey] ?? 0;

        $hints = [];
        if ($lowestAvg < 1.5) {
            $hints[] = '📉 Чаще всего проседает '.$dimensions[$lowestKey]['label'].' — можно чуть чаще обращать на это внимание.';
        }

        $todayScore = $this->scoreForDay($user, $now);
        if ($todayScore >= self::MAX_DAILY_POINTS) {
            $hints[] = '🌟 Сегодня максимум баллов — супер дисциплина!';
        } elseif ($todayScore >= 7) {
            $hints[] = '💪 Сегодня почти идеально — отличный день, так держать!';
        } elseif ($todayScore >= 5) {
            $hints[] = '✨ Хороший день: в целом всё неплохо, мелочи ещё можно подтянуть.';
        } elseif ($todayScore > 0) {
            $hints[] = '📌 Баллы есть, но завтра можно сделать ровнее — без «досыпания вчера», только вперёд.';
        }

        return $hints ?: ['⚖️ Пока всё в балансе — продолжай в том же духе!'];
    }

    public function formatSummaryMessage(User $user, ?Carbon $now = null): string
    {
        $s = $this->summary($user, $now);
        $streak = $this->checkInStreakDays($user, $now);
        $lines = [
            '📊 <b>Твой рейтинг дисциплины</b>',
            '',
            '📅 Сегодня: '.$s['day'].' / '.self::MAX_DAILY_POINTS,
            '🗓 Неделя: '.$s['week'].' баллов',
            '📆 Месяц: '.$s['month'].' баллов',
            '🔥 Дней в ударе подряд: '.$streak,
            '',
            ...$this->weakAreasFeedback($user, 7, $now),
        ];

        return implode("\n", $lines);
    }

    /**
     * Подряд идущие календарные дни с завершённым чек-ином.
     * Если сегодня ещё не отмечен — счёт начинается с вчера.
     */
    public function checkInStreakDays(User $user, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $streak = 0;
        $day = $now->copy()->startOfDay();

        if (! $this->hasCompletedCheckOnDate($user, $day)) {
            $day->subDay();
        }

        while ($this->hasCompletedCheckOnDate($user, $day)) {
            $streak++;
            $day->subDay();
        }

        return $streak;
    }

    public function hasCompletedCheckOnDate(User $user, Carbon $day): bool
    {
        return $user->dailyChecks()
            ->whereDate('check_date', $day->toDateString())
            ->where('is_completed', true)
            ->exists();
    }

    /**
     * Самая слабая ось в конкретном чек-ине (null — если всё на максимуме по баллам).
     */
    public function weakestDimensionLabelForCheck(DailyCheck $check): ?string
    {
        $dims = [
            'питание' => $this->pointsForRating($check->diet_rating),
            'сон' => $this->pointsForRating($check->sleep_rating),
            'тренировки' => $this->pointsForRating($check->workout_rating),
            'вода' => $this->pointsForRating($check->water_rating),
        ];
        $min = min($dims);
        if ($min >= 2) {
            return null;
        }

        $weak = array_keys(array_filter($dims, fn (int $p) => $p === $min));
        if (count($weak) >= 3) {
            return 'несколько пунктов';
        }
        if (count($weak) === 2) {
            return $weak[0].' и '.$weak[1];
        }

        return $weak[0];
    }

    /** Последняя дата завершённого чек-ина строго до указанной календарной даты (Y-m-d). */
    public function lastCompletedCheckDateBefore(User $user, string $beforeDate): ?string
    {
        $max = $user->dailyChecks()
            ->where('is_completed', true)
            ->whereDate('check_date', '<', $beforeDate)
            ->max('check_date');

        return $max !== null ? (string) $max : null;
    }
}
