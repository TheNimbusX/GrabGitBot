<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramTestConnectionCommand extends Command
{
    protected $signature = 'telegram:test-api';

    protected $description = 'Проверка getMe к api.telegram.org (учитывает TELEGRAM_HTTP_PROXY)';

    public function handle(TelegramBotService $telegram): int
    {
        if ((string) config('telegram.bot_token') === '') {
            $this->error('Задай TELEGRAM_BOT_TOKEN в .env');

            return self::FAILURE;
        }

        $proxy = config('telegram.http_proxy');
        if (is_string($proxy) && $proxy !== '') {
            $this->info('TELEGRAM_HTTP_PROXY задан (значение в .env, пароль в консоль не выводим).');
        } else {
            $this->warn('Прокси не задан — прямое подключение к api.telegram.org.');
        }

        $this->line('Запрос getMe...');
        $me = $telegram->getMe();
        if ($me === null) {
            $this->error('Не удалось. Смотри storage/logs/laravel.log (часто cURL 28 = таймаут без прокси).');

            return self::FAILURE;
        }

        $this->info('Успех: @'.($me['username'] ?? '?').' (id '.($me['id'] ?? '?').')');

        return self::SUCCESS;
    }
}
