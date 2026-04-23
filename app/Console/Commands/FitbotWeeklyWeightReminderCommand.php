<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FitBot\FitBotMessaging;
use App\Services\Telegram\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class FitbotWeeklyWeightReminderCommand extends Command
{
    protected $signature = 'fitbot:weekly-weight-reminder';

    protected $description = 'Раз в неделю: напоминание обновить вес (тихие часы и флаг уведомлений)';

    public function handle(TelegramBotService $telegram): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан');

            return self::FAILURE;
        }

        $now = Carbon::now();
        $text = FitBotMessaging::weeklyWeightReminderNudge();
        $markup = $telegram->inlineKeyboard([
            [['text' => '⚖️ Обновить вес', 'callback_data' => 'wt:start']],
        ]);
        $sent = 0;

        $this->completedOnboardingUsers()->chunkById(100, function ($users) use ($telegram, $now, $text, $markup, &$sent) {
            foreach ($users as $user) {
                if ($user->weight_kg === null) {
                    continue;
                }
                if (! $user->allowsBotPushAt($now, 'weekly_weight')) {
                    continue;
                }

                try {
                    $telegram->sendMessage((int) $user->telegram_id, $text, $markup);
                } catch (\Throwable $e) {
                    Log::warning('fitbot weekly weight reminder failed', [
                        'user_id' => $user->id,
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }
                $sent++;
            }
        });

        $this->info("Напоминаний про вес: {$sent}");

        return self::SUCCESS;
    }

    /** @return Builder<User> */
    private function completedOnboardingUsers(): Builder
    {
        return User::query()->completedFitbotOnboarding();
    }
}
