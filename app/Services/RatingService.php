<?php

namespace App\Services;

use App\Enums\CheckRating;
use App\Enums\WorkoutCheckVariant;
use App\Models\DailyCheck;
use App\Models\User;
use App\Services\FitBot\FitBotMessaging;
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
            return ['📭 Пока мало данных - отметь несколько чек-инов, и я подскажу, что чаще проседает.'];
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
            $hints[] = '📉 Чаще всего проседает '.$dimensions[$lowestKey]['label'].' - можно чуть чаще обращать на это внимание.';
        }

        $todayScore = $this->scoreForDay($user, $now);
        if ($todayScore >= self::MAX_DAILY_POINTS) {
            $hints[] = '🌟 Сегодня максимум баллов - супер дисциплина!';
        } elseif ($todayScore >= 7) {
            $hints[] = '💪 Сегодня почти идеально - отличный день, так держать!';
        } elseif ($todayScore >= 5) {
            $hints[] = '✨ Хороший день: в целом всё неплохо, мелочи ещё можно подтянуть.';
        } elseif ($todayScore > 0) {
            $hints[] = '📌 Баллы есть, но завтра можно сделать ровнее - без «досыпания вчера», только вперёд.';
        }

        return $hints ?: ['⚖️ Пока всё в балансе - продолжай в том же духе!'];
    }

    /**
     * Текст фокуса без префикса «Фокус недели» — для строки «Сейчас: …» в настройках и после смены фокуса.
     */
    public function weeklyFocusContentHtml(User $user, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        $note = $user->weekly_focus_note;
        if (is_string($note) && trim($note) !== '') {
            return e(trim($note));
        }

        $from = $now->copy()->subDays(7)->startOfDay();
        $completedCount = $user->dailyChecks()
            ->where('is_completed', true)
            ->where('check_date', '>=', $from->toDateString())
            ->count();

        if ($completedCount < 2) {
            return 'закрепи <b>регулярный чек-ин</b> - увидишь, что тянуть первым.';
        }

        /** @var Collection<int, DailyCheck> $checks */
        $checks = $user->dailyChecks()
            ->where('is_completed', true)
            ->where('check_date', '>=', $from->toDateString())
            ->get();

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
        $lowestAvg = $avg[$lowestKey] ?? 0.0;
        $label = $dimensions[$lowestKey]['label'] ?? 'ритм';

        if ($lowestAvg >= 1.75) {
            return 'держи <b>баланс и регулярность</b> - по последним дням оси ровные.';
        }

        return 'чуть чаще проседает <b>'.$label.'</b> - имеет смысл присмотреться к этой оси.';
    }

    /** Одна строка для чек-ина /rating: свой текст или авто по слабой оси за ~7 дней. */
    public function weeklyFocusLine(User $user, ?Carbon $now = null): string
    {
        return '🎯 <b>Фокус недели:</b> '.$this->weeklyFocusContentHtml($user, $now);
    }

    public function formatSummaryMessage(User $user, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        $s = $this->summary($user, $now);
        $streak = $this->checkInStreakDays($user, $now);
        $todayDone = $this->hasCompletedCheckOnDate($user, $now->copy()->startOfDay());
        $streakBanner = FitBotMessaging::streakCoreBanner($streak, $todayDone);
        $workoutNudge = FitBotMessaging::workoutSkippedStreakNudge(
            $this->consecutiveSkippedWorkoutDays($user, $now)
        );

        $lines = [
            '📊 <b>Твой рейтинг дисциплины</b>',
        ];
        if ($streakBanner !== null) {
            $lines[] = '';
            $lines[] = $streakBanner;
        }
        if ($workoutNudge !== null) {
            $lines[] = '';
            $lines[] = $workoutNudge;
        }
        $lines[] = '';
        $lines = array_merge($lines, [
            '📅 Сегодня: '.$s['day'].' / '.self::MAX_DAILY_POINTS,
            '🗓 Неделя: '.$s['week'].' баллов',
            '📆 Месяц: '.$s['month'].' баллов',
            '',
            ...$this->weakAreasFeedback($user, 7, $now),
            '',
            $this->weeklyFocusLine($user, $now),
        ]);

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

    public function completedCheckOnDate(User $user, Carbon $day): ?DailyCheck
    {
        return $user->dailyChecks()
            ->whereDate('check_date', $day->toDateString())
            ->where('is_completed', true)
            ->first();
    }

    /**
     * Подряд завершённых дней, где в чек-ине выбрано «прогулял тренировку» (цепочка обрывается на «позанимался» или «отдых»).
     */
    public function consecutiveSkippedWorkoutDays(User $user, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $n = 0;
        $day = $now->copy()->startOfDay();

        if (! $this->hasCompletedCheckOnDate($user, $day)) {
            $day->subDay();
        }

        while ($this->hasCompletedCheckOnDate($user, $day)) {
            $check = $this->completedCheckOnDate($user, $day);
            if ($check === null) {
                break;
            }
            $v = WorkoutCheckVariant::tryFrom((string) ($check->workout_variant ?? ''));
            if ($v === WorkoutCheckVariant::Skipped) {
                $n++;
                $day->subDay();
            } else {
                break;
            }
        }

        return $n;
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

    /**
     * Расширенная сводка: месяц как дневник чек-инов, чтобы быстро видеть провалы по осям.
     */
    public function formatExtendedAnalyticsMessage(User $user, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        $windowFrom = $now->copy()->subDays(29)->startOfDay();
        $registeredFrom = $user->created_at?->copy()->startOfDay() ?? $windowFrom->copy();
        $from = $registeredFrom->gt($windowFrom) ? $registeredFrom : $windowFrom;
        $to = $now->copy()->startOfDay();
        $calendarDays = (int) $from->diffInDays($to) + 1;
        $periodTitle = $calendarDays < 30 ? 'С регистрации' : 'Последние 30 дней';

        /** @var Collection<int, DailyCheck> $checks30 */
        $checks30 = $user->dailyChecks()
            ->where('is_completed', true)
            ->where('check_date', '>=', $from->toDateString())
            ->where('check_date', '<=', $to->toDateString())
            ->orderBy('check_date')
            ->get();
        $checksByDate = $checks30->keyBy(fn (DailyCheck $c) => $c->check_date->toDateString());

        if ($checks30->isEmpty()) {
            return "📈 <b>Расширенная аналитика</b>\n\n"
                .'Пока нет завершённых чек-инов - отметь хотя бы несколько дней, и здесь появятся цифры.';
        }

        $completedDays = $checks30->count();
        $totalPts = (int) $checks30->sum('total_score');
        $avgIfMarked = $completedDays > 0 ? round($totalPts / $completedDays, 1) : 0.0;
        $pctMarked = (int) round(100 * $completedDays / $calendarDays);
        $missedDays = $calendarDays - $completedDays;
        $perfectDays = $checks30->filter(fn (DailyCheck $c) => (int) $c->total_score === self::MAX_DAILY_POINTS)->count();
        $lowDays = $checks30->filter(fn (DailyCheck $c) => (int) $c->total_score <= 3)->count();

        $workoutCounts = [
            'trained' => 0,
            'rest' => 0,
            'recovery' => 0,
            'skipped' => 0,
        ];
        $trainedDates = [];
        $skippedWorkoutDates = [];
        foreach ($checks30 as $c) {
            $v = WorkoutCheckVariant::tryFrom((string) ($c->workout_variant ?? ''));
            if ($v === WorkoutCheckVariant::Trained) {
                $workoutCounts['trained']++;
                $trainedDates[] = $c->check_date->format('d.m');
            } elseif ($v === WorkoutCheckVariant::Rest) {
                $workoutCounts['rest']++;
            } elseif ($v === WorkoutCheckVariant::Recovery) {
                $workoutCounts['recovery']++;
            } elseif ($v === WorkoutCheckVariant::Skipped) {
                $workoutCounts['skipped']++;
                $skippedWorkoutDates[] = $c->check_date->format('d.m');
            }
        }

        $wStart = $now->copy()->startOfWeek();
        $wEnd = $now->copy()->endOfWeek();
        $lwStart = $wStart->copy()->subWeek();
        $lwEnd = $wEnd->copy()->subWeek();
        $thisWeekPts = $this->scoreForPeriod($user, $wStart, $wEnd);
        $lastWeekPts = $this->scoreForPeriod($user, $lwStart, $lwEnd);

        $best = $checks30->sortByDesc('total_score')->first();
        $worst = $checks30->sortBy('total_score')->first();
        $bestStr = $best ? $best->check_date->format('d.m').': <b>'.$best->total_score.'</b>' : '-';
        $worstStr = $worst ? $worst->check_date->format('d.m').': <b>'.$worst->total_score.'</b>' : '-';
        $streak = $this->checkInStreakDays($user, $now);
        $todayDone = $this->hasCompletedCheckOnDate($user, $now->copy()->startOfDay());
        $streakBanner = FitBotMessaging::streakCoreBanner($streak, $todayDone);
        $workoutNudge = FitBotMessaging::workoutSkippedStreakNudge(
            $this->consecutiveSkippedWorkoutDays($user, $now)
        );

        $lines = [
            '📈 <b>Расширенная аналитика</b>',
            '<i>'.$periodTitle.' · '.$from->format('d.m').' - '.$to->format('d.m').'</i>',
        ];
        if ($streakBanner !== null) {
            $lines[] = '';
            $lines[] = $streakBanner;
        }
        if ($workoutNudge !== null) {
            $lines[] = '';
            $lines[] = $workoutNudge;
        }
        $lines[] = '';
        $lines = array_merge($lines, [
            '━━━ <b>Итог периода</b>',
            '✅ Чек-инов: <b>'.$completedDays.'</b> из '.$calendarDays.' ('.$pctMarked.'%) · пропусков: <b>'.$missedDays.'</b>',
            '⭐ Баллы: <b>'.$totalPts.'</b> · средний отмеченный день: <b>'.$avgIfMarked.'</b>/'.self::MAX_DAILY_POINTS,
            '🏆 Идеальных дней: <b>'.$perfectDays.'</b> · слабых (0-3): <b>'.$lowDays.'</b>',
            '📆 Неделя: эта <b>'.$thisWeekPts.'</b> · прошлая <b>'.$lastWeekPts.'</b>',
            'Пик: '.$bestStr.' · просадка: '.$worstStr,
            '',
            '━━━ <b>Оси</b>',
            '🍽 Еда: '.$this->axisScoreSummary($checks30, 'diet'),
            '😴 Сон: '.$this->axisScoreSummary($checks30, 'sleep'),
            '💧 Вода: '.$this->axisScoreSummary($checks30, 'water'),
            '',
            '━━━ <b>Движение</b>',
            '💪 Тренировки: <b>'.$workoutCounts['trained'].'</b> · '.$this->formatDateList($trainedDates),
            '😴 Отдых: <b>'.$workoutCounts['rest'].'</b> · 🤒 восстановление: <b>'.$workoutCounts['recovery'].'</b> · ❌ пропуск: <b>'.$workoutCounts['skipped'].'</b>',
            'Пропуски движения: '.$this->formatDateList($skippedWorkoutDates),
            '',
            '━━━ <b>Календарь периода</b>',
            '<i>еда · сон · движение · вода · сумма</i>',
        ]);
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            /** @var DailyCheck|null $check */
            $check = $checksByDate->get($day->toDateString());
            $lines[] = $this->calendarLineForDay($day, $check);
        }

        $lines = array_merge($lines, $this->extendedAnalyticsWeightLines($user));

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, DailyCheck>  $checks
     */
    private function axisScoreSummary(Collection $checks, string $axis): string
    {
        $getter = match ($axis) {
            'diet' => fn (DailyCheck $c) => $c->diet_rating,
            'sleep' => fn (DailyCheck $c) => $c->sleep_rating,
            'workout' => fn (DailyCheck $c) => $c->workout_rating,
            'water' => fn (DailyCheck $c) => $c->water_rating,
            default => fn (DailyCheck $c) => null,
        };
        $green = 0;
        $yellow = 0;
        $red = 0;
        $sum = 0;
        foreach ($checks as $check) {
            $rating = CheckRating::tryFrom((string) $getter($check));
            if ($rating === null) {
                continue;
            }
            $sum += $rating->points();
            if ($rating === CheckRating::Green) {
                $green++;
            } elseif ($rating === CheckRating::Yellow) {
                $yellow++;
            } else {
                $red++;
            }
        }
        $avg = $checks->isEmpty() ? 0 : round($sum / max(1, $checks->count()), 1);

        return 'ср. <b>'.$avg.'</b>/2 · 🟢'.$green.' 🟡'.$yellow.' 🔴'.$red;
    }

    /** @param list<string> $dates */
    private function formatDateList(array $dates, int $limit = 8): string
    {
        if ($dates === []) {
            return '—';
        }
        $shown = array_slice($dates, 0, $limit);
        $suffix = count($dates) > $limit ? ' +'.(count($dates) - $limit) : '';

        return implode(', ', $shown).$suffix;
    }

    private function calendarLineForDay(Carbon $day, ?DailyCheck $check): string
    {
        $date = '<code>'.$day->format('d.m').'</code>';
        if ($check === null) {
            return $date.' — чек-ин не закрыт';
        }
        $workout = WorkoutCheckVariant::tryFrom((string) $check->workout_variant);

        return $date
            .' еда '.$this->ratingDot($check->diet_rating)
            .' · сон '.$this->ratingDot($check->sleep_rating)
            .' · движение '.$this->workoutMark($workout)
            .' · вода '.$this->ratingDot($check->water_rating)
            .' = <b>'.(int) $check->total_score.'</b>';
    }

    private function ratingDot(?string $rating): string
    {
        return CheckRating::tryFrom((string) $rating)?->emoji() ?? '⚪️';
    }

    private function workoutMark(?WorkoutCheckVariant $variant): string
    {
        return match ($variant) {
            WorkoutCheckVariant::Trained => '💪',
            WorkoutCheckVariant::Rest => '😴',
            WorkoutCheckVariant::Recovery => '🤒',
            WorkoutCheckVariant::Skipped => '❌',
            default => '⚪️',
        };
    }

    /** @return list<string> */
    private function extendedAnalyticsWeightLines(User $user): array
    {
        if ($user->weight_kg === null) {
            return [];
        }

        $out = [
            '',
            '<b>Вес</b>',
            'Сейчас: <b>'.round((float) $user->weight_kg, 1).'</b> кг',
        ];

        $start = $user->starting_weight_kg;
        if ($start === null) {
            $first = $user->weightLogs()->orderBy('created_at')->first();
            $start = $first !== null ? (float) $first->weight_kg : null;
        }

        if ($start !== null) {
            $delta = round((float) $user->weight_kg - (float) $start, 1);
            $sign = $delta > 0 ? '+' : '';
            $out[] = 'Старт (анкета): <b>'.round((float) $start, 1).'</b> кг · изменение: <b>'.$sign.$delta.'</b> кг';
        }

        $recent = $user->weightLogs()->orderByDesc('created_at')->limit(5)->get();
        if ($recent->count() >= 2) {
            $parts = [];
            foreach ($recent->sortBy('created_at')->values() as $log) {
                $parts[] = $log->created_at->format('d.m').' <b>'.round((float) $log->weight_kg, 1).'</b>';
            }
            $out[] = 'Замеры: '.implode(' · ', $parts);
        }

        return $out;
    }
}
