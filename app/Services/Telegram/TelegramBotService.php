<?php

namespace App\Services\Telegram;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBotService
{
    private static ?Client $botJsonClient = null;

    public function apiUrl(string $method): string
    {
        $token = (string) config('telegram.bot_token');

        return 'https://api.telegram.org/bot'.$token.'/'.$method;
    }

    /** @return array<string, mixed> */
    private function httpOptions(): array
    {
        $proxy = config('telegram.http_proxy');
        if ($proxy === null || $proxy === '') {
            return [];
        }

        return ['proxy' => $proxy];
    }

    private function http(): PendingRequest
    {
        return Http::withOptions($this->httpOptions());
    }

    /**
     * Один клиент на воркер PHP-FPM: повторное TLS к api.telegram.org не на каждый sendMessage.
     */
    private function botJsonClient(): Client
    {
        if (self::$botJsonClient !== null) {
            return self::$botJsonClient;
        }

        $token = (string) config('telegram.bot_token');
        $opts = [
            'base_uri' => 'https://api.telegram.org/bot'.$token.'/',
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::CONNECT_TIMEOUT => 12,
            RequestOptions::TIMEOUT => 55,
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        $proxy = config('telegram.http_proxy');
        if (is_string($proxy) && $proxy !== '') {
            $opts[RequestOptions::PROXY] = $proxy;
        }

        if (\defined('CURLOPT_TCP_KEEPALIVE')) {
            $opts['curl'] = [
                \CURLOPT_TCP_KEEPALIVE => 1,
                \CURLOPT_TCP_KEEPIDLE => 60,
                \CURLOPT_TCP_KEEPINTVL => 30,
            ];
        }

        self::$botJsonClient = new Client($opts);

        return self::$botJsonClient;
    }

    /** @return array<string, mixed>|null */
    public function getMe(): ?array
    {
        try {
            $response = $this->http()
                ->connectTimeout(15)
                ->timeout(30)
                ->get($this->apiUrl('getMe'));
        } catch (Throwable $e) {
            Log::warning('Telegram getMe exception', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Telegram getMe HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $json = $response->json();
        if (! ($json['ok'] ?? false)) {
            Log::warning('Telegram getMe not ok', ['body' => $json]);

            return null;
        }

        return is_array($json['result'] ?? null) ? $json['result'] : null;
    }

    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null, ?string $parseMode = 'HTML'): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }
        if ($parseMode === null) {
            unset($payload['parse_mode']);
        }

        $this->post('sendMessage', $payload);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null) {
            $payload['text'] = $text;
        }
        $this->post('answerCallbackQuery', $payload);
    }

    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }
        $this->post('editMessageText', $payload);
    }

    /** @param list<list<array{text: string, callback_data: string}>> $rows */
    public function inlineKeyboard(array $rows): array
    {
        return [
            'inline_keyboard' => array_map(
                fn (array $row) => array_map(
                    fn (array $btn) => ['text' => $btn['text'], 'callback_data' => $btn['callback_data']],
                    $row
                ),
                $rows
            ),
        ];
    }

    /**
     * Обычная клавиатура под полем ввода (всегда на виду, в отличие от inline).
     *
     * @param list<list<array{text: string}>> $rows
     * @return array<string, mixed>
     */
    public function replyKeyboard(array $rows, bool $resizeKeyboard = true): array
    {
        return [
            'keyboard' => array_map(
                fn (array $row) => array_map(
                    fn (array $btn) => ['text' => $btn['text']],
                    $row
                ),
                $rows
            ),
            'resize_keyboard' => $resizeKeyboard,
        ];
    }

    /** @return array<string, bool> */
    public function replyKeyboardRemove(): array
    {
        return ['remove_keyboard' => true];
    }

    /** Клавиатура главного меню FitBot (чек-ин, рейтинг, план). */
    public function fitbotMainMenuKeyboard(): array
    {
        return $this->replyKeyboard([
            [
                ['text' => 'Чек-ин'],
                ['text' => 'Рейтинг'],
                ['text' => '📋 План'],
            ],
            [
                ['text' => '⚙️ Настройки'],
                ['text' => '📈 Расширенная аналитика'],
            ],
            [
                ['text' => '👉 Персональный план (AI)'],
            ],
        ]);
    }

    public function deleteWebhook(bool $dropPendingUpdates = true): bool
    {
        try {
            $response = $this->http()
                ->connectTimeout(20)
                ->timeout(120)
                ->asForm()
                ->post($this->apiUrl('deleteWebhook'), [
                    'drop_pending_updates' => $dropPendingUpdates ? 'true' : 'false',
                ]);
        } catch (Throwable $e) {
            Log::warning('Telegram deleteWebhook exception', ['message' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Telegram deleteWebhook failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return (bool) ($response->json('ok'));
    }

    /**
     * Long polling: держит соединение до timeout секунд (макс. 50).
     *
     * @return list<array<string, mixed>>
     */
    public function getUpdates(int $offset, int $timeout = 50): array
    {
        $timeout = max(0, min(50, $timeout));
        try {
            $response = $this->http()
                ->connectTimeout(20)
                ->timeout($timeout + 30)
                ->asForm()
                ->post($this->apiUrl('getUpdates'), [
                    'offset' => $offset,
                    'timeout' => $timeout,
                    'allowed_updates' => json_encode(['message', 'callback_query']),
                ]);
        } catch (Throwable $e) {
            Log::warning('Telegram getUpdates exception', ['message' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Telegram getUpdates HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $json = $response->json();
        if (! ($json['ok'] ?? false)) {
            Log::warning('Telegram getUpdates not ok', ['body' => $json]);

            return [];
        }

        return $json['result'] ?? [];
    }

    private function post(string $method, array $payload): void
    {
        try {
            $response = $this->botJsonClient()->post($method, [RequestOptions::JSON => $payload]);
        } catch (Throwable $e) {
            Log::warning('Telegram API exception', [
                'method' => $method,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            Log::warning('Telegram API error', [
                'method' => $method,
                'status' => $status,
                'body' => $body,
            ]);

            return;
        }

        $json = json_decode($body, true);
        if (! is_array($json) || ! ($json['ok'] ?? false)) {
            Log::warning('Telegram API ok=false', [
                'method' => $method,
                'description' => is_array($json) ? ($json['description'] ?? null) : null,
                'body' => $body,
            ]);
        }
    }
}
