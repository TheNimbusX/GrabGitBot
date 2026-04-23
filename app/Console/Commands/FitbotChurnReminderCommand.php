<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FitBot\FitBotMessaging;
use App\Services\Telegram\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FitbotChurnReminderCommand extends Command
{
    protected $signature = 'fitbot:churn-reminder';

    protected $description = 'Re-engagement: пользователь давно не завершал чек-ин (2+ / 4+ дня)';

    public function handle(TelegramBotService $telegram): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан');

            return self::FAILURE;
        }

        $today = now()->startOfDay();
        $sentSoft = 0;
        $sentHard = 0;

        $this->completedOnboardingUsers()->chunkById(100, function ($users) use ($telegram, $today, &$sentSoft, &$sentHard) {
            foreach ($users as $user) {
                if (! $user->allowsBotPushAt(Carbon::now(), 'churn')) {
                    continue;
                }

                $last = $user->dailyChecks()
                    ->where('is_completed', true)
                    ->max('check_date');

                if ($last === null) {
                    continue;
                }

                $lastDay = Carbon::parse($last)->startOfDay();
                $daysSince = $lastDay->diffInDays($today);

                if ($daysSince < 2) {
                    continue;
                }

                $baseKey = (string) $last;

                if ($daysSince >= 4) {
                    $cacheKey = "fitbot:churn_hard:{$user->id}:{$baseKey}";
                    if (Cache::has($cacheKey)) {
                        continue;
                    }
                    $text = FitBotMessaging::churnAfterFourDays();
                    $ttl = now()->addDays(60);
                } else {
                    $cacheKey = "fitbot:churn_soft:{$user->id}:{$baseKey}";
                    if (Cache::has($cacheKey)) {
                        continue;
                    }
                    $text = FitBotMessaging::churnAfterTwoDays();
                    $ttl = now()->addDays(30);
                }

                try {
                    $telegram->sendMessage((int) $user->telegram_id, $text, $telegram->fitbotMainMenuKeyboard());
                } catch (\Throwable $e) {
                    Log::warning('fitbot churn reminder send failed', [
                        'user_id' => $user->id,
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }

                Cache::put($cacheKey, true, $ttl);
                if ($daysSince >= 4) {
                    $sentHard++;
                } else {
                    $sentSoft++;
                }
            }
        });

        $this->info('Churn мягких: '.$sentSoft.', жёстких: '.$sentHard);

        return self::SUCCESS;
    }

    /** @return Builder<User> */
    private function completedOnboardingUsers(): Builder
    {
        return User::query()->completedFitbotOnboarding();
    }
}
