<?php

namespace App\Console\Commands;

use App\Enums\StrikeStatusTier;
use App\Models\User;
use App\Services\FitBot\FitBotMessaging;
use App\Services\RatingService;
use App\Services\Telegram\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class FitbotMorningMotivationCommand extends Command
{
    protected $signature = 'fitbot:morning-motivation';

    protected $description = 'Утро: мягкий тон 1-2 день, крючок 3-6, день 7 отдельно, дальше ровный пул';

    public function handle(TelegramBotService $telegram, RatingService $rating): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан');

            return self::FAILURE;
        }

        $dateKey = now()->toDateString();
        $now = Carbon::now();
        $sent = 0;

        $this->completedOnboardingUsers()->chunkById(100, function ($users) use ($telegram, $rating, $dateKey, $now, &$sent) {
            foreach ($users as $user) {
                if (! $user->allowsBotPushAt($now, 'morning')) {
                    continue;
                }

                $dayNum = FitBotMessaging::dayNumberInBot($user, $now);

                if ($dayNum === 7) {
                    $text = FitBotMessaging::morningDay7();
                } elseif ($dayNum <= 2) {
                    $text = FitBotMessaging::pickStable($dateKey, (int) $user->telegram_id, FitBotMessaging::morningSoftPool());
                } elseif ($dayNum <= 6) {
                    $text = FitBotMessaging::pickStable($dateKey, (int) $user->telegram_id, FitBotMessaging::morningHookPool());
                } else {
                    $text = FitBotMessaging::pickStable($dateKey, (int) $user->telegram_id, FitBotMessaging::morningLongRunPool());
                }

                $prefix = [];
                $streak = $rating->checkInStreakDays($user, $now);
                $todayDone = $rating->hasCompletedCheckOnDate($user, $now->copy()->startOfDay());
                $streakBanner = FitBotMessaging::streakCoreBanner($streak, $todayDone);
                if ($streakBanner !== null) {
                    $prefix[] = $streakBanner;
                }
                $gym = FitBotMessaging::workoutSkippedStreakNudge($rating->consecutiveSkippedWorkoutDays($user, $now));
                if ($gym !== null) {
                    $prefix[] = $gym;
                }
                if ($now->dayOfWeek === Carbon::MONDAY) {
                    $streakMon = $rating->checkInStreakDays($user, $now);
                    $monStatus = FitBotMessaging::strikeStatusMondayLine(
                        $streakMon,
                        StrikeStatusTier::fromCheckInStreak($streakMon)
                    );
                    if ($monStatus !== null) {
                        $prefix[] = $monStatus;
                    }
                }
                if ($prefix !== []) {
                    $text = implode("\n\n", $prefix)."\n\n".$text;
                }

                try {
                    $telegram->sendMessage((int) $user->telegram_id, $text, $telegram->fitbotMainMenuKeyboard());
                } catch (\Throwable $e) {
                    Log::warning('fitbot morning motivation send failed', [
                        'user_id' => $user->id,
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }
                $sent++;
            }
        });

        $this->info("Утренних сообщений: {$sent}");

        return self::SUCCESS;
    }

    /** @return Builder<User> */
    private function completedOnboardingUsers(): Builder
    {
        return User::query()->completedFitbotOnboarding();
    }
}
