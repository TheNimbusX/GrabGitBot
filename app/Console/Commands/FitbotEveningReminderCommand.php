<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RatingService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FitbotEveningReminderCommand extends Command
{
    protected $signature = 'fitbot:evening-reminder';

    protected $description = 'Вечернее напоминание занести день в /check (если чек-ин сегодня не завершён)';

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
                $text = "🌙 <b>Вечер!</b> Пора записать день в FitBot — пройди <b>/check</b>.\n\n"
                    .'Серия «дней в ударе»: <b>'.$streak.'</b>. '
                    .'Если сегодня не завершишь чек-ин, серия обнулится.';

                $telegram->sendMessage((int) $user->telegram_id, $text, $telegram->replyKeyboard([
                    [
                        ['text' => 'Чек-ин'],
                        ['text' => 'Рейтинг'],
                        ['text' => '⚙️ Настройки'],
                    ],
                    [
                        ['text' => '👉 Персональный план (AI)'],
                    ],
                ]));
                $sent++;
            }
        });

        $this->info("Вечерних напоминаний: {$sent}");

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
