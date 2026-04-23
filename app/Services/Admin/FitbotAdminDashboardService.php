<?php

namespace App\Services\Admin;

use App\Enums\OnboardingStep;
use App\Models\DailyCheck;
use App\Models\Photo;
use App\Models\TelegramOutboundMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FitbotAdminDashboardService
{
    private const TABLE_LIMIT = 350;

    /** @return array<string, mixed> */
    public function build(Request $request): array
    {
        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek()->toDateString();
        $activeSince = $now->copy()->subDays(6)->startOfDay()->toDateString();

        $stats = $this->aggregateStats($now, $weekStart, $activeSince);

        $q = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', 'all');
        $sort = (string) $request->query('sort', 'id_desc');

        $userQuery = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($qq) use ($term, $q) {
                    $qq->where('first_name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                    if (ctype_digit($q)) {
                        $qq->orWhere('telegram_id', (int) $q)->orWhere('id', (int) $q);
                    }
                });
            });

        if ($filter === 'completed') {
            $userQuery->completedFitbotOnboarding();
        } elseif ($filter === 'onboarding') {
            $userQuery->whereNotNull('onboarding_step')->where('onboarding_step', '!=', '');
        } elseif ($filter === 'active7') {
            $userQuery->whereHas('dailyChecks', function ($qq) use ($activeSince) {
                $qq->where('is_completed', true)
                    ->where('check_date', '>=', $activeSince);
            });
        } elseif ($filter === 'inactive7') {
            $userQuery->completedFitbotOnboarding()
                ->whereDoesntHave('dailyChecks', function ($qq) use ($activeSince) {
                    $qq->where('is_completed', true)
                        ->where('check_date', '>=', $activeSince);
                });
        } elseif ($filter === 'new7') {
            $userQuery->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay());
        }

        $userQuery
            ->withCount([
                'dailyChecks as completed_checks_count' => fn ($qq) => $qq->where('is_completed', true),
            ])
            ->withCount(['photos as photos_count'])
            ->withSum([
                'dailyChecks as lifetime_points' => fn ($qq) => $qq->where('is_completed', true),
            ], 'total_score')
            ->withSum([
                'dailyChecks as week_points' => fn ($qq) => $qq->where('is_completed', true)
                    ->where('check_date', '>=', $activeSince),
            ], 'total_score')
            ->withMax([
                'dailyChecks as last_check_date' => fn ($qq) => $qq->where('is_completed', true),
            ], 'check_date')
            ->orderByDesc('id');

        $fetchLimit = in_array($sort, ['streak_desc', 'streak_asc'], true) ? 800 : self::TABLE_LIMIT;
        $users = $userQuery->limit($fetchLimit)->get();

        $userIds = $users->pluck('id')->all();
        $datesByUserId = $this->loadCompletedCheckDatesByUser($userIds);

        $rows = $users->map(function (User $user) use ($datesByUserId, $now) {
            $dateRows = $datesByUserId->get($user->id, collect());
            $dateStrings = $dateRows->map(function ($r) {
                $d = $r->check_date;

                return $d instanceof Carbon ? $d->toDateString() : Carbon::parse((string) $d)->toDateString();
            })->unique()->values();

            $lastCheck = null;
            if ($user->last_check_date !== null && $user->last_check_date !== '') {
                $lastCheck = $user->last_check_date instanceof Carbon
                    ? $user->last_check_date
                    : Carbon::parse((string) $user->last_check_date);
            }

            $completedN = (int) ($user->completed_checks_count ?? 0);
            $lifePts = (float) ($user->lifetime_points ?? 0);

            return [
                'user' => $user,
                'streak' => $this->computeStreakDays($dateStrings, $now),
                'last_check' => $lastCheck,
                'onboarding_done' => $user->hasCompletedOnboarding(),
                'completed_checks' => $completedN,
                'photos_count' => (int) ($user->photos_count ?? 0),
                'lifetime_points' => (int) $lifePts,
                'week_points' => (int) ($user->week_points ?? 0),
                'avg_check_score' => $completedN > 0 ? round($lifePts / $completedN, 2) : null,
            ];
        });

        if ($sort === 'streak_desc') {
            $rows = $rows->sortByDesc(fn (array $r) => $r['streak'])->values();
        } elseif ($sort === 'streak_asc') {
            $rows = $rows->sortBy(fn (array $r) => $r['streak'])->values();
        } elseif ($sort === 'checks_desc') {
            $rows = $rows->sortByDesc(fn (array $r) => $r['completed_checks'])->values();
        } elseif ($sort === 'points_week_desc') {
            $rows = $rows->sortByDesc(fn (array $r) => $r['week_points'])->values();
        } elseif ($sort === 'last_check_desc') {
            $rows = $rows->sortByDesc(fn (array $r) => $r['last_check']?->timestamp ?? 0)->values();
        } elseif ($sort === 'created_asc') {
            $rows = $rows->sortBy(fn (array $r) => $r['user']->created_at?->timestamp ?? 0)->values();
        } else {
            $rows = $rows->sortByDesc(fn (array $r) => $r['user']->id)->values();
        }

        $rows = $rows->take(self::TABLE_LIMIT)->values();

        return [
            'stats' => $stats,
            'rows' => $rows,
            'filters' => [
                'q' => $q,
                'filter' => $filter,
                'sort' => $sort,
            ],
            'onboardingFunnel' => $this->onboardingFunnelCounts(),
        ];
    }

    /** @return array<string, int|float|string> */
    private function aggregateStats(Carbon $now, string $weekStart, string $activeSince): array
    {
        $completedScope = User::query()->completedFitbotOnboarding();

        $checksCompleted = (int) DailyCheck::query()->where('is_completed', true)->count();
        $checksToday = (int) DailyCheck::query()
            ->where('is_completed', true)
            ->whereDate('check_date', $now->toDateString())
            ->count();
        $checksWeek = (int) DailyCheck::query()
            ->where('is_completed', true)
            ->where('check_date', '>=', $weekStart)
            ->count();

        $pointsWeekAll = (int) DailyCheck::query()
            ->where('is_completed', true)
            ->where('check_date', '>=', $weekStart)
            ->sum('total_score');

        $avgScoreWeek = DailyCheck::query()
            ->where('is_completed', true)
            ->where('check_date', '>=', $weekStart)
            ->avg('total_score');

        $usersActive7 = (int) User::query()
            ->whereHas('dailyChecks', function ($q) use ($activeSince) {
                $q->where('is_completed', true)->where('check_date', '>=', $activeSince);
            })
            ->count();

        $usersNew7 = (int) User::query()
            ->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay())
            ->count();

        $photosTotal = (int) Photo::query()->count();
        $loggedMsgs = 0;
        if (Schema::hasTable('telegram_outbound_messages')) {
            $loggedMsgs = (int) TelegramOutboundMessage::query()->count();
        }

        $planFull = (int) User::query()->where('plan_mode', 'full')->count();
        $planDisc = (int) User::query()->where('plan_mode', 'discipline')->count();
        $planLegacy = (int) User::query()
            ->whereNull('plan_mode')
            ->whereNotNull('daily_calories_target')
            ->count();

        return [
            'users_total' => User::query()->count(),
            'users_completed_onboarding' => (clone $completedScope)->count(),
            'users_in_onboarding' => User::query()
                ->whereNotNull('onboarding_step')
                ->where('onboarding_step', '!=', '')
                ->count(),
            'checks_completed_total' => $checksCompleted,
            'checks_today' => $checksToday,
            'checks_week' => $checksWeek,
            'points_week_all_users' => $pointsWeekAll,
            'avg_score_per_check_week' => $avgScoreWeek !== null ? round((float) $avgScoreWeek, 2) : null,
            'users_active_7d' => $usersActive7,
            'users_new_7d' => $usersNew7,
            'photos_total' => $photosTotal,
            'telegram_logged_messages' => $loggedMsgs,
            'plan_mode_full' => $planFull,
            'plan_mode_discipline' => $planDisc,
            'plan_legacy_calories' => $planLegacy,
        ];
    }

    /** @return Collection<int, Collection<int, object{check_date: string}>> */
    private function loadCompletedCheckDatesByUser(array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return DailyCheck::query()
            ->whereIn('user_id', $userIds)
            ->where('is_completed', true)
            ->orderBy('check_date')
            ->get(['user_id', 'check_date'])
            ->groupBy('user_id');
    }

    /**
     * @param  Collection<int, string>  $uniqueDateStrings  Y-m-d
     */
    private function computeStreakDays(Collection $uniqueDateStrings, Carbon $now): int
    {
        $set = array_fill_keys($uniqueDateStrings->all(), true);
        $day = $now->copy()->startOfDay();
        if (! isset($set[$day->toDateString()])) {
            $day->subDay();
        }
        $streak = 0;
        while (isset($set[$day->toDateString()])) {
            $streak++;
            $day->subDay();
        }

        return $streak;
    }

    /** @return list<array{key: string, label: string, count: int}> */
    private function onboardingFunnelCounts(): array
    {
        $raw = User::query()
            ->whereNotNull('onboarding_step')
            ->where('onboarding_step', '!=', '')
            ->select('onboarding_step', DB::raw('count(*) as c'))
            ->groupBy('onboarding_step')
            ->pluck('c', 'onboarding_step');

        $out = [];
        foreach (OnboardingStep::cases() as $step) {
            $c = (int) ($raw[$step->value] ?? 0);
            if ($c > 0) {
                $out[] = [
                    'key' => $step->value,
                    'label' => $this->onboardingStepLabel($step),
                    'count' => $c,
                ];
            }
        }

        usort($out, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    private function onboardingStepLabel(OnboardingStep $step): string
    {
        return match ($step) {
            OnboardingStep::AskWelcome => 'Приветствие',
            OnboardingStep::AskPlanChoice => 'Выбор плана',
            OnboardingStep::AskGender => 'Пол',
            OnboardingStep::AskAge => 'Возраст',
            OnboardingStep::AskWeight => 'Вес',
            OnboardingStep::AskHeight => 'Рост',
            OnboardingStep::AskActivity => 'Активность',
            OnboardingStep::AskGoal => 'Цель',
            OnboardingStep::AskExperience => 'Опыт',
            OnboardingStep::AskSleep => 'Сон',
            OnboardingStep::AskWaterGoal => 'Вода',
            OnboardingStep::AskBeforePhoto => 'Фото «до»',
        };
    }
}
