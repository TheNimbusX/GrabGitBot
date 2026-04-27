<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RatingService;
use App\Services\Telegram\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FitbotClubWeeklyReportCommand extends Command
{
    protected $signature = 'fitbot:club-weekly-report';

    protected $description = 'Рассылает weekly-отчёт активным участникам FitBot Club';

    public function handle(TelegramBotService $telegram, RatingService $rating): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан');

            return self::FAILURE;
        }

        $now = Carbon::now();
        $sent = 0;
        User::query()
            ->completedFitbotOnboarding()
            ->where('fitbot_club_until', '>', $now)
            ->chunkById(100, function ($users) use ($telegram, $rating, $now, &$sent) {
                foreach ($users as $user) {
                    if (! $user->allowsBotPushAt($now, 'weekly_focus')) {
                        continue;
                    }
                    $cacheKey = 'fitbot:club_weekly_report:'.$user->id.':'.$now->format('o-W');
                    if (Cache::has($cacheKey)) {
                        continue;
                    }

                    try {
                        $telegram->sendMessage((int) $user->telegram_id, $rating->formatClubWeeklyReportMessage($user));
                    } catch (\Throwable $e) {
                        Log::warning('fitbot club weekly report failed', [
                            'user_id' => $user->id,
                            'message' => $e->getMessage(),
                        ]);

                        continue;
                    }
                    Cache::put($cacheKey, true, now()->addDays(14));
                    $sent++;
                }
            });

        $this->info("Weekly-отчётов клуба отправлено: {$sent}");

        return self::SUCCESS;
    }
}
