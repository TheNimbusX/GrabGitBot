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
            return ['Пока мало данных — отметьте несколько чек-инов, и я смогу подсказать, что проседает.'];
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
            $hints[] = 'Чаще всего проседает '.$dimensions[$lowestKey]['label'].' — имеет смысл сфокусироваться на нём.';
        }

        $todayScore = $this->scoreForDay($user, $now);
        if ($todayScore >= self::MAX_DAILY_POINTS) {
            $hints[] = 'Сегодня отличная дисциплина.';
        } elseif ($todayScore > 0) {
            $hints[] = 'Сегодняшний день можно добить до максимума — проверьте, что ещё не на «идеально».';
        }

        return $hints ?: ['Пока всё в балансе — продолжайте в том же духе.'];
    }

    public function formatSummaryMessage(User $user, ?Carbon $now = null): string
    {
        $s = $this->summary($user, $now);
        $lines = [
            'Твой рейтинг дисциплины:',
            '• Сегодня: '.$s['day'].' / '.self::MAX_DAILY_POINTS,
            '• Неделя: '.$s['week'].' баллов',
            '• Месяц: '.$s['month'].' баллов',
            '',
            ...$this->weakAreasFeedback($user, 7, $now),
        ];

        return implode("\n", $lines);
    }
}
