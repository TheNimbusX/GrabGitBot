<?php

namespace App\Console\Commands;

use App\Services\FitBot\FitBotService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll
                            {--timeout=50 : Long polling, секунды 0–50}
                            {--keep-webhook : Не вызывать deleteWebhook (если вебхук уже снят вручную)}';

    protected $description = 'Long polling Telegram: getUpdates в цикле (для VPS, где вебхук с Telegram не доходит)';

    public function handle(FitBotService $fitBot, TelegramBotService $telegram): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('Задай TELEGRAM_BOT_TOKEN в .env');

            return self::FAILURE;
        }

        if (! $this->option('keep-webhook')) {
            $this->info('Снимаю webhook (drop_pending_updates=true)...');
            if ($telegram->deleteWebhook(true)) {
                $this->info('Webhook снят.');
            } else {
                $this->warn('deleteWebhook вернул ошибку — проверь токен и сеть до api.telegram.org');
            }
        }

        $timeout = (int) $this->option('timeout');
        $timeout = max(0, min(50, $timeout));
        $offset = 0;

        $this->info("Long polling (timeout={$timeout}s), Ctrl+C для остановки.");

        while (true) {
            try {
                $updates = $telegram->getUpdates($offset, $timeout);
                foreach ($updates as $u) {
                    try {
                        $fitBot->handleUpdate($u);
                    } catch (Throwable $e) {
                        Log::error('telegram poll handleUpdate', [
                            'message' => $e->getMessage(),
                            'update_id' => $u['update_id'] ?? null,
                        ]);
                    }
                    $offset = max($offset, (int) ($u['update_id'] ?? 0) + 1);
                }
            } catch (Throwable $e) {
                Log::error('telegram poll getUpdates', ['message' => $e->getMessage()]);
                sleep(5);
            }
        }

        return self::SUCCESS;
    }
}
