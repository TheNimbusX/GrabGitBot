<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\FitbotAdminDashboardService;
use App\Services\Telegram\TelegramBotService;
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
