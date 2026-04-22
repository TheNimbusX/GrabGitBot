<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FitbotMorningMotivationCommand extends Command
{
    protected $signature = 'fitbot:morning-motivation';

    protected $description = 'Утренняя мотивация для активных пользователей';

    /** @var list<string> */
    private const MESSAGES = [
        '☀️ Давай, бро — не потеряй этот день. Сделай своё тело чуточку лучше, чем вчера 💪',
        '☀️ Утро задаёт тон: вода, завтрак, движение. Ты справишься — вперёд.',
        '☀️ Каждый день с чистого листа. Шаг за шагом к цели — ты на ходу.',
        '☀️ Маленькие усилия складываются в большой результат. Не сливай день.',
        '☀️ Твоё будущее «я» скажет спасибо за то, что ты не сдался сегодня.',
        '☀️ Дисциплина — это любовь к себе в действии. Удачного дня!',
        '☀️ Не жди идеального момента — создай его. Хорошей тренировки или прогулки.',
        '☀️ Сила не в идеале, а в регулярности. Ты уже на правильном пути.',
        '☀️ Сделай сегодня тело сильнее, а голову спокойнее. Ты можешь больше, чем думаешь.',
        '☀️ Один осознанный выбор за другим — через месяц ты удивишься прогрессу.',
        '☀️ Ты строишь привычки, не спринт. Сегодня — отличный день для маленькой победы.',
        '☀️ Вечером загляни в бота и запиши день — так видно, как далеко ты зашёл.',
        '☀️ Не сравнивай себя с другими — сравнивай с вчерашним собой. Ты растёшь.',
        '☀️ Заботься о себе так, как о лучшем друге. Хорошего дня и ровного настроя.',
        '☀️ Каждый чек-ин — честность с собой. Сегодня ты можешь сделать день осознанным.',
    ];

    public function handle(TelegramBotService $telegram): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан');

            return self::FAILURE;
        }

        $dateKey = now()->toDateString();
        $sent = 0;

        $this->completedOnboardingUsers()->chunkById(100, function ($users) use ($telegram, $dateKey, &$sent) {
            foreach ($users as $user) {
                $idx = crc32((string) $user->telegram_id.$dateKey) % count(self::MESSAGES);
                $text = self::MESSAGES[$idx];
                $telegram->sendMessage((int) $user->telegram_id, $text, $telegram->replyKeyboard([
                    [
                        ['text' => 'Чек-ин'],
                        ['text' => 'Рейтинг'],
                        ['text' => '📋 План'],
                    ],
                    [
                        ['text' => '⚙️ Настройки'],
                    ],
                    [
                        ['text' => '👉 Персональный план (AI)'],
                    ],
                ]));
                $sent++;
            }
        });

        $this->info("Утренних сообщений: {$sent}");

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
