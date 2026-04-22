<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RatingService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FitbotDailyReminderCommand extends Command
{
    protected $signature = 'fitbot:daily-reminder';

    protected $description = 'Утреннее напоминание о /check (если сегодня чек-ин ещё не завершён)';

    public function handle(TelegramBotService $telegram, RatingService $rating): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан');

            return self::FAILURE;
        }

        $today = now()->toDateString();
        $sent = 0;

        $this->completedOnboardingUsers()->chunkById(100, function ($users) use ($telegram, $rating, $today, &$sent) {
            foreach ($users as $user) {
                $hasToday = $user->dailyChecks()
                    ->whereDate('check_date', $today)
                    ->where('is_completed', true)
                    ->exists();

                if ($hasToday) {
                    continue;
                }

                $streak = $rating->checkInStreakDays($user);
                $text = "⏰ <b>Доброе утро!</b> Не забудь пройти чек-ин — /check\n\n"
                    .'Серия «дней в ударе»: <b>'.$streak.'</b>. '
                    .'Если сегодня не завершишь чек-ин, серия обнулится.';

                $telegram->sendMessage((int) $user->telegram_id, $text);
                $sent++;
            }
        });

        $this->info("Отправлено напоминаний: {$sent}");

        return self::SUCCESS;
    }

    /** @return Builder<User> */
    private function completedOnboardingUsers(): Builder
    {
        return User::query()
            ->where(function ($q) {
                $q->whereNull('onboarding_step')->orWhere('onboarding_step', '');
            })
            ->whereNotNull('daily_calories_target');
    }
}
