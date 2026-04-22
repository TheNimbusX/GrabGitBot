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
                            {--keep-webhook : Не вызывать deleteWebhook (сними вебхук вручную с ПК, если с VPS api.telegram.org недоступен)}';

    protected $description = 'Long polling Telegram: getUpdates в цикле (для VPS, где вебхук от Telegram не доходит)';

    public function handle(FitBotService $fitBot, TelegramBotService $telegram): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('Задай TELEGRAM_BOT_TOKEN в .env');

            return self::FAILURE;
        }

        $me = $telegram->getMe();
        if ($me === null) {
            $this->error('Telegram getMe не прошёл.');
            $this->line('Если в storage/logs/laravel.log — cURL error 28 (timeout) до api.telegram.org: с этого VPS нет исходящего доступа к Bot API (как на части РФ/KZ-сетей). Нужны TELEGRAM_HTTP_PROXY, VPN или VPS в другом регионе.');
            $this->line('Если в логе HTTP 401 — неверный TELEGRAM_BOT_TOKEN или устарел config:cache (php artisan config:clear && php artisan config:cache).');

            return self::FAILURE;
        }
        $this->info(
            'Бот: @'.($me['username'] ?? '?').' (id '.($me['id'] ?? '?').').'
        );

        if (config('telegram.http_proxy')) {
            $this->info('Используется TELEGRAM_HTTP_PROXY.');
        } else {
            $this->warn('Без TELEGRAM_HTTP_PROXY: если до api.telegram.org таймаут — задай прокси в .env или VPS за РФ.');
        }

        if (! $this->option('keep-webhook')) {
            $this->info('Снимаю webhook (drop_pending_updates=true)...');
            if ($telegram->deleteWebhook(true)) {
                $this->info('Webhook снят.');
            } else {
                $this->warn('deleteWebhook с VPS не прошёл. Открой с ПК (подставь токен):');
                $this->line('https://api.telegram.org/bot<ТОКЕН>/deleteWebhook?drop_pending_updates=true');
                $this->line('Затем: docker compose run --rm app php artisan telegram:poll --keep-webhook');
            }
        }

        $timeout = (int) $this->option('timeout');
        $timeout = max(0, min(50, $timeout));
        $offset = 0;

        $this->info("Long polling (timeout={$timeout}s), Ctrl+C для остановки.");

        while (true) {
            try {
                $updates = $telegram->getUpdates($offset, $timeout);
                if ($updates !== []) {
                    $this->info('Апдейтов: '.count($updates));
                }
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
