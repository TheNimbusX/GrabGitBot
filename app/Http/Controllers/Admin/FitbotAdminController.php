<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserPlanMode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\FitbotAdminDashboardService;
use App\Services\RatingService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class FitbotAdminController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session('fitbot_admin')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login', [
            'configured' => (string) config('fitbot.admin_password') !== '',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|string']);

        $expected = (string) config('fitbot.admin_password');
        if ($expected === '') {
            return back()->withErrors(['password' => 'Пароль админки не задан в .env (FITBOT_ADMIN_PASSWORD).']);
        }

        if (! hash_equals($expected, (string) $request->input('password'))) {
            return back()->withErrors(['password' => 'Неверный пароль.']);
        }

        $request->session()->put('fitbot_admin', true);

        return redirect()->route('admin.dashboard');
    }

    public function dashboard(Request $request, FitbotAdminDashboardService $dashboard): View
    {
        return view('admin.dashboard', $dashboard->build($request));
    }

    public function broadcast(Request $request, TelegramBotService $telegram, RatingService $rating): RedirectResponse
    {
        $segments = [
            'all_completed',
            'in_onboarding',
            'active_7d',
            'inactive_7d',
            'inactive_14d',
            'new_7d',
            'completed_never_checked',
            'streak_3_plus',
            'plan_full',
            'discipline_only',
            'low_activity_14d',
        ];

        $data = $request->validate([
            'message' => 'required|string|max:4090',
            'segment' => 'nullable|string|in:'.implode(',', $segments),
        ]);

        $text = $data['message'];
        $segment = $data['segment'] ?? 'all_completed';
        $n = 0;

        if ($segment === 'streak_3_plus') {
            User::query()->completedFitbotOnboarding()->chunkById(80, function ($users) use ($telegram, $rating, $text, &$n) {
                foreach ($users as $user) {
                    if ($rating->checkInStreakDays($user) < 3) {
                        continue;
                    }
                    $telegram->sendMessage((int) $user->telegram_id, $text, null, null);
                    $n++;
                    usleep(40000);
                }
            });
        } else {
            $q = $this->broadcastRecipientsQuery($segment);
            if ($q === null) {
                return back()->withErrors(['segment' => 'Неизвестный сегмент.']);
            }

            $q->chunkById(80, function ($users) use ($telegram, $text, &$n) {
                foreach ($users as $user) {
                    $telegram->sendMessage((int) $user->telegram_id, $text, null, null);
                    $n++;
                    usleep(40000);
                }
            });
        }

        return back()->with('broadcast_status', "Сегмент «{$segment}»: отправлено {$n} пользователям.");
    }

    /** @return Builder<User>|null */
    private function broadcastRecipientsQuery(string $segment): ?Builder
    {
        $since7 = now()->subDays(7)->toDateString();
        $since14 = now()->subDays(14)->toDateString();

        $q = User::query();

        return match ($segment) {
            'all_completed' => $q->completedFitbotOnboarding(),
            'in_onboarding' => $q->whereNotNull('onboarding_step')->where('onboarding_step', '!=', ''),
            'active_7d' => $q->completedFitbotOnboarding()->whereHas('dailyChecks', function (Builder $c) use ($since7) {
                $c->where('is_completed', true)->where('check_date', '>=', $since7);
            }),
            'inactive_7d' => $q->completedFitbotOnboarding()->whereDoesntHave('dailyChecks', function (Builder $c) use ($since7) {
                $c->where('is_completed', true)->where('check_date', '>=', $since7);
            }),
            'inactive_14d' => $q->completedFitbotOnboarding()->whereDoesntHave('dailyChecks', function (Builder $c) use ($since14) {
                $c->where('is_completed', true)->where('check_date', '>=', $since14);
            }),
            'new_7d' => $q->where('created_at', '>=', now()->subDays(7)),
            'completed_never_checked' => $q->completedFitbotOnboarding()->whereDoesntHave('dailyChecks', function (Builder $c) {
                $c->where('is_completed', true);
            }),
            'plan_full' => $q->completedFitbotOnboarding()->where(function (Builder $w) {
                $w->where('plan_mode', UserPlanMode::Full->value)
                    ->orWhere(function (Builder $w2) {
                        $w2->whereNull('plan_mode')->whereNotNull('daily_calories_target');
                    });
            }),
            'discipline_only' => $q->completedFitbotOnboarding()->where('plan_mode', UserPlanMode::Discipline->value),
            'low_activity_14d' => $q->completedFitbotOnboarding()->whereRaw(
                '(select count(*) from daily_checks where daily_checks.user_id = users.id and is_completed = 1 and check_date >= ?) <= 1',
                [$since14]
            ),
            default => null,
        };
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('fitbot_admin');

        return redirect()->route('admin.login');
    }

    public function destroyUser(Request $request, User $user, TelegramBotService $telegram): RedirectResponse
    {
        if ((string) config('telegram.bot_token') === '') {
            return back()->withErrors(['delete' => 'TELEGRAM_BOT_TOKEN не задан — удаление из Telegram невозможно.']);
        }

        $request->validate([
            'confirm_delete' => 'required|accepted',
        ]);

        $telegramId = (int) $user->telegram_id;
        $notifyUser = $request->boolean('notify_user');

        try {
            $telegram->deleteRecordedOutboundMessagesForUser($user);
        } catch (Throwable $e) {
            Log::warning('admin purge telegram messages failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }

        if ($notifyUser) {
            try {
                $telegram->sendMessage(
                    $telegramId,
                    'Аккаунт удалён администратором. Чтобы начать заново, отправь /start.',
                    null,
                    null
                );
            } catch (Throwable $e) {
                Log::warning('admin delete user notify failed', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Cache::forget('fitbot:account_delete:'.$telegramId);

        $label = $user->first_name ?? (string) $user->id;
        $user->delete();

        return back()->with('admin_status', "Пользователь {$label} (telegram {$telegramId}) удалён; сообщения бота в чате очищены насколько позволяет Telegram.");
    }
}
