<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FitBot\FitBotMessaging;
use App\Services\RatingService;
use App\Services\Telegram\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FitbotEveningReminderCommand extends Command
{
    protected $signature = 'fitbot:evening-reminder {--follow-up : Жёсткое напоминание тем, кто получил мягкое ~10 мин назад и всё ещё без чек-ина}';

    protected $description = 'Вечернее напоминание: чек-ин не завершён сегодня (мягкое; с --follow-up — второе сообщение)';

    public function handle(TelegramBotService $telegram, RatingService $rating): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан');

            return self::FAILURE;
        }

        $today = Carbon::today()->toDateString();
        $todayStart = Carbon::today();
        $followUp = (bool) $this->option('follow-up');
        $sent = 0;

        $this->completedOnboardingUsers()->chunkById(100, function ($users) use ($telegram, $rating, $today, $todayStart, $followUp, &$sent) {
            foreach ($users as $user) {
                $hasToday = $user->dailyChecks()
                    ->whereDate('check_date', $today)
                    ->where('is_completed', true)
                    ->exists();

                if ($hasToday) {
                    continue;
                }

                $softKey = "fitbot:evening_soft:{$user->id}:{$today}";
                $strictKey = "fitbot:evening_strict:{$user->id}:{$today}";

                if ($followUp) {
                    if (! Cache::has($softKey) || Cache::has($strictKey)) {
                        continue;
                    }
                    $text = FitBotMessaging::eveningReminderStrict();
                } else {
                    if (Cache::has($softKey)) {
                        continue;
                    }
                    $text = FitBotMessaging::eveningReminderSoft($rating, $user, $todayStart);
                }

                try {
                    $telegram->sendMessage((int) $user->telegram_id, $text, $telegram->fitbotMainMenuKeyboard());
                } catch (\Throwable $e) {
                    Log::warning('fitbot evening reminder send failed', [
                        'user_id' => $user->id,
                        'follow_up' => $followUp,
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }

                if ($followUp) {
                    Cache::put($strictKey, true, now()->endOfDay());
                } else {
                    Cache::put($softKey, true, now()->endOfDay());
                }
                $sent++;
            }
        });

        $this->info($followUp ? "Жёстких напоминаний: {$sent}" : "Мягких вечерних напоминаний: {$sent}");

        return self::SUCCESS;
    }

    /** @return Builder<User> */
    private function completedOnboardingUsers(): Builder
    {
        return User::query()->completedFitbotOnboarding();
    }
}
