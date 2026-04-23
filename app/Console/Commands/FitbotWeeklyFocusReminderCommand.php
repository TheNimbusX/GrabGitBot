<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FitBot\FitBotMessaging;
use App\Services\Telegram\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class FitbotWeeklyFocusReminderCommand extends Command
{
    protected $signature = 'fitbot:weekly-focus-reminder';

    protected $description = 'Раз в неделю: напоминание обновить фокус недели (с учётом тихих часов и флага)';

    public function handle(TelegramBotService $telegram): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан');

            return self::FAILURE;
        }

        $now = Carbon::now();
        $text = FitBotMessaging::weeklyFocusReminderNudge();
        $sent = 0;

        $this->completedOnboardingUsers()->chunkById(100, function ($users) use ($telegram, $now, $text, &$sent) {
            foreach ($users as $user) {
                if (! $user->allowsBotPushAt($now, 'weekly_focus')) {
                    continue;
                }

                try {
                    $telegram->sendMessage((int) $user->telegram_id, $text, $telegram->fitbotMainMenuKeyboard());
                } catch (\Throwable $e) {
                    Log::warning('fitbot weekly focus reminder failed', [
                        'user_id' => $user->id,
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }
                $sent++;
            }
        });

        $this->info("Напоминаний про фокус: {$sent}");

        return self::SUCCESS;
    }

    /** @return Builder<User> */
    private function completedOnboardingUsers(): Builder
    {
        return User::query()->completedFitbotOnboarding();
    }
}
