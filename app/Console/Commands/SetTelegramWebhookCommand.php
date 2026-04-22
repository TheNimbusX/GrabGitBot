<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
                            {url : Полный HTTPS URL вебхука, например https://abc.ngrok-free.app/telegram/webhook}
                            {--secret= : Секрет для заголовка X-Telegram-Bot-Api-Secret-Token (запиши то же значение в TELEGRAM_WEBHOOK_SECRET)}';

    protected $description = 'Регистрирует webhook у Telegram Bot API';

    public function handle(): int
    {
        $token = (string) config('telegram.bot_token');
        if ($token === '') {
            $this->error('В .env не задан TELEGRAM_BOT_TOKEN.');

            return self::FAILURE;
        }

        $url = rtrim((string) $this->argument('url'), '/');
        if (! str_starts_with($url, 'https://')) {
            $this->error('URL должен начинаться с https:// (Telegram так требует).');

            return self::FAILURE;
        }

        $payload = ['url' => $url];
        $secret = $this->option('secret');
        if (is_string($secret) && $secret !== '') {
            $payload['secret_token'] = $secret;
        }

        $response = Http::asForm()->post(
            'https://api.telegram.org/bot'.$token.'/setWebhook',
            $payload
        );

        if (! $response->successful()) {
            $this->error('HTTP '.$response->status().': '.$response->body());

            return self::FAILURE;
        }

        $body = $response->json();
        if (! ($body['ok'] ?? false)) {
            $this->error('Telegram ответил с ошибкой: '.json_encode($body, JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }

        $this->info('Webhook установлен: '.$url);
        if (! empty($payload['secret_token'])) {
            $this->warn('Добавь в .env: TELEGRAM_WEBHOOK_SECRET='.$payload['secret_token']);
        }

        return self::SUCCESS;
    }
}
