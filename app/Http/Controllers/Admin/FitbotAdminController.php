<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RatingService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    public function dashboard(RatingService $rating): View
    {
        $completed = User::query()->completedFitbotOnboarding();

        $stats = [
            'users_total' => User::query()->count(),
            'users_completed_onboarding' => (clone $completed)->count(),
            'users_in_onboarding' => User::query()
                ->whereNotNull('onboarding_step')
                ->where('onboarding_step', '!=', '')
                ->count(),
        ];

        $rows = User::query()
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(function (User $user) use ($rating) {
                $last = $user->dailyChecks()
                    ->where('is_completed', true)
                    ->orderByDesc('check_date')
                    ->first();

                return [
                    'user' => $user,
                    'streak' => $rating->checkInStreakDays($user),
                    'last_check' => $last?->check_date,
                    'onboarding_done' => $user->hasCompletedOnboarding(),
                ];
            });

        return view('admin.dashboard', compact('stats', 'rows'));
    }

    public function broadcast(Request $request, TelegramBotService $telegram): RedirectResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:4090',
        ]);

        $text = $data['message'];
        $n = 0;

        User::query()->completedFitbotOnboarding()->chunkById(80, function ($users) use ($telegram, $text, &$n) {
            foreach ($users as $user) {
                $telegram->sendMessage((int) $user->telegram_id, $text, null, null);
                $n++;
                usleep(40000);
            }
        });

        return back()->with('broadcast_status', "Отправлено пользователям: {$n}");
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('fitbot_admin');

        return redirect()->route('admin.login');
    }
}
