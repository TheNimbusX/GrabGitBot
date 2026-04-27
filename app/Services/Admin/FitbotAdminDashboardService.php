<?php

namespace App\Services\Admin;

use App\Enums\OnboardingStep;
use App\Enums\StrikeStatusTier;
use App\Models\DailyCheck;
use App\Models\Photo;
use App\Models\TelegramOutboundMessage;
use App\Models\User;
use App\Models\UserSupportMessage;
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
        $since14 = $now->copy()->subDays(14)->toDateString();

        $stats = $this->aggregateStats($now, $weekStart, $activeSince, $since14);

        $q = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', 'all');
        $sort = (string) $request->query('sort', 'id_desc');
        $clubColumnExists = Schema::hasColumn('users', 'fitbot_club_until');

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
        } elseif ($filter === 'inactive14') {
            $userQuery->completedFitbotOnboarding()
                ->whereDoesntHave('dailyChecks', function ($qq) use ($since14) {
                    $qq->where('is_completed', true)
                        ->where('check_date', '>=', $since14);
                });
        } elseif ($filter === 'never_checked') {
            $userQuery->completedFitbotOnboarding()
                ->whereDoesntHave('dailyChecks', function ($qq) {
                    $qq->where('is_completed', true);
                });
        } elseif ($filter === 'low_activity_14d') {
            $userQuery->completedFitbotOnboarding()->whereRaw(
                '(select count(*) from daily_checks where daily_checks.user_id = users.id and is_completed = 1 and check_date >= ?) <= 1',
                [$since14]
            );
        } elseif ($filter === 'club_active' && $clubColumnExists) {
            $userQuery->where('fitbot_club_until', '>', $now);
        } elseif ($filter === 'club_expired' && $clubColumnExists) {
            $userQuery->whereNotNull('fitbot_club_until')->where('fitbot_club_until', '<=', $now);
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

        $rows = $users->map(function (User $user) use ($datesByUserId, $now, $clubColumnExists) {
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
            $onboardingDone = $user->hasCompletedOnboarding();
            $daysSinceCheck = $lastCheck !== null
                ? (int) $lastCheck->copy()->startOfDay()->diffInDays($now->copy()->startOfDay())
                : null;

            $streakDays = $this->computeStreakDays($dateStrings, $now);

            return [
                'user' => $user,
                'streak' => $streakDays,
                'strike_tier' => StrikeStatusTier::fromCheckInStreak($streakDays),
                'last_check' => $lastCheck,
                'days_since_check' => $daysSinceCheck,
                'pulse' => $this->userEngagementPulse($user, $onboardingDone, $daysSinceCheck, $now),
                'onboarding_done' => $onboardingDone,
                'onboarding_hint' => ! $onboardingDone ? $this->onboardingStepLabelForValue($user->onboarding_step) : null,
                'completed_checks' => $completedN,
                'photos_count' => (int) ($user->photos_count ?? 0),
                'lifetime_points' => (int) $lifePts,
                'week_points' => (int) ($user->week_points ?? 0),
                'avg_check_score' => $completedN > 0 ? round($lifePts / $completedN, 2) : null,
                'days_in_bot' => $user->created_at !== null
                    ? (int) $now->copy()->startOfDay()->diffInDays($user->created_at->copy()->startOfDay())
                    : null,
                'last_message_to_bot' => $user->last_message_to_bot_at,
                'days_since_message_to_bot' => $user->last_message_to_bot_at !== null
                    ? (int) $user->last_message_to_bot_at->copy()->startOfDay()->diffInDays($now->copy()->startOfDay())
                    : null,
                'club_active' => $clubColumnExists && $user->isFitbotClubActive($now),
                'club_until' => $clubColumnExists ? $user->fitbot_club_until : null,
                'club_days_left' => $clubColumnExists && $user->fitbot_club_until !== null && $user->fitbot_club_until->isFuture()
                    ? (int) $now->copy()->startOfDay()->diffInDays($user->fitbot_club_until->copy()->startOfDay()) + 1
                    : null,
                'club_founder' => $clubColumnExists && (bool) $user->fitbot_club_founder,
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
        } elseif ($sort === 'last_message_desc') {
            $rows = $rows->sortByDesc(fn (array $r) => $r['last_message_to_bot']?->timestamp ?? 0)->values();
        } elseif ($sort === 'created_asc') {
            $rows = $rows->sortBy(fn (array $r) => $r['user']->created_at?->timestamp ?? 0)->values();
        } else {
            $rows = $rows->sortByDesc(fn (array $r) => $r['user']->id)->values();
        }

        $rows = $rows->take(self::TABLE_LIMIT)->values();

        $supportTableExists = Schema::hasTable('user_support_messages');
        $supportHasReadAt = $supportTableExists && Schema::hasColumn('user_support_messages', 'read_at');
        $supportMessages = $supportTableExists
            ? UserSupportMessage::query()
                ->with(['user:id,first_name,username,telegram_id'])
                ->when($supportHasReadAt, fn ($q) => $q->orderByRaw('read_at is null desc'))
                ->orderByDesc('id')
                ->limit(100)
                ->get()
            : collect();
        $supportMessagesTotal = $supportTableExists
            ? (int) UserSupportMessage::query()->count()
            : 0;
        $supportUnreadCount = $supportTableExists && $supportHasReadAt
            ? (int) UserSupportMessage::query()->whereNull('read_at')->count()
            : null;

        return [
            'stats' => $stats,
            'rows' => $rows,
            'filters' => [
                'q' => $q,
                'filter' => $filter,
                'sort' => $sort,
            ],
            'onboardingFunnel' => $this->onboardingFunnelCounts(),
            'generatedAt' => $now->copy(),
            'supportMessages' => $supportMessages,
            'supportMessagesTotal' => $supportMessagesTotal,
            'supportUnreadCount' => $supportUnreadCount,
            'supportHasReadAt' => $supportHasReadAt,
            'clubColumnExists' => $clubColumnExists,
        ];
    }

    /** @return array<string, int|float|string|null> */
    private function aggregateStats(Carbon $now, string $weekStart, string $activeSince, string $since14): array
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

        $usersCompletedN = (int) (clone $completedScope)->count();
        $usersActive7AmongCompleted = (int) (clone $completedScope)
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

        $usersDormant7 = (int) (clone $completedScope)
            ->whereDoesntHave('dailyChecks', function ($q) use ($activeSince) {
                $q->where('is_completed', true)->where('check_date', '>=', $activeSince);
            })
            ->count();
        $usersDormant14 = (int) (clone $completedScope)
            ->whereDoesntHave('dailyChecks', function ($q) use ($since14) {
                $q->where('is_completed', true)->where('check_date', '>=', $since14);
            })
            ->count();
        $usersNeverChecked = (int) User::query()->completedFitbotOnboarding()
            ->whereDoesntHave('dailyChecks', function ($q) {
                $q->where('is_completed', true);
            })
            ->count();
        $clubActive = Schema::hasColumn('users', 'fitbot_club_until')
            ? (int) User::query()->where('fitbot_club_until', '>', $now)->count()
            : 0;

        $engagementOfCompletedPct = $usersCompletedN > 0
            ? round(100 * $usersActive7AmongCompleted / $usersCompletedN, 1)
            : null;

        return [
            'users_total' => User::query()->count(),
            'users_completed_onboarding' => $usersCompletedN,
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
            'users_active_7d_completed' => $usersActive7AmongCompleted,
            'users_new_7d' => $usersNew7,
            'users_dormant_7d_completed' => $usersDormant7,
            'users_dormant_14d_completed' => $usersDormant14,
            'users_completed_never_checked' => $usersNeverChecked,
            'fitbot_club_active' => $clubActive,
            'engagement_completed_7d_pct' => $engagementOfCompletedPct,
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

    private function userEngagementPulse(User $user, bool $onboardingDone, ?int $daysSinceCheck, Carbon $now): string
    {
        if (! $onboardingDone) {
            return 'onboarding';
        }
        if ($user->created_at && $user->created_at->gte($now->copy()->subDays(7)->startOfDay())) {
            return 'new';
        }
        if ($daysSinceCheck === null) {
            return 'cold';
        }
        if ($daysSinceCheck <= 1) {
            return 'hot';
        }
        if ($daysSinceCheck <= 6) {
            return 'warm';
        }
        if ($daysSinceCheck <= 13) {
            return 'cool';
        }

        return 'cold';
    }

    private function onboardingStepLabelForValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return $this->onboardingStepLabel(OnboardingStep::from($value));
        } catch (\ValueError) {
            return $value;
        }
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
