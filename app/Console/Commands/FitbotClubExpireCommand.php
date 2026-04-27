<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Telegram\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FitbotClubExpireCommand extends Command
{
    protected $signature = 'fitbot:club-expire {--dry-run : Только показать, кого нужно удалить из чата}';

    protected $description = 'Контролирует истёкшие CLUB-доступы и удаляет пользователей из закрытого чата';

    public function handle(TelegramBotService $telegram): int
    {
        $chatId = config('fitbot.club_chat_id');
        if ($chatId === null || $chatId === '') {
            $this->warn('FITBOT_CLUB_CHAT_ID не задан, удаление из чата пропущено.');

            return self::SUCCESS;
        }

        $now = Carbon::now();
        $removed = 0;
        $dryRun = (bool) $this->option('dry-run');
        User::query()
            ->whereNotNull('fitbot_club_until')
            ->where('fitbot_club_until', '<=', $now)
            ->whereNull('fitbot_club_chat_removed_at')
            ->chunkById(100, function ($users) use ($telegram, $chatId, $dryRun, &$removed) {
                foreach ($users as $user) {
                    if ($dryRun) {
                        $this->line("DRY-RUN: {$user->id} / {$user->telegram_id} / club_until={$user->fitbot_club_until?->format('Y-m-d H:i:s')}");
                        $removed++;

                        continue;
                    }
                    if (! $telegram->removeUserFromChat($chatId, (int) $user->telegram_id)) {
                        continue;
                    }
                    $user->fitbot_club_chat_removed_at = now();
                    $user->save();
                    $removed++;

                    try {
                        $telegram->sendMessage(
                            (int) $user->telegram_id,
                            '🏁 Доступ в FitBot Club закончился. Базовые функции бота остаются доступны.'
                        );
                    } catch (\Throwable $e) {
                        Log::warning('club expire notify failed', [
                            'user_id' => $user->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info($dryRun ? "К удалению из CLUB-чата: {$removed}" : "Удалено из CLUB-чата: {$removed}");

        return self::SUCCESS;
    }
}
