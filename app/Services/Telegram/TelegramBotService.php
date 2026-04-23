<?php

namespace App\Services\Telegram;

use App\Models\TelegramOutboundMessage;
use App\Models\User;
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

        $json = $this->postJson('sendMessage', $payload);
        if (! is_array($json) || ! ($json['ok'] ?? false)) {
            return;
        }
        $result = $json['result'] ?? null;
        if (is_array($result) && isset($result['message_id'])) {
            $this->recordOutboundChatMessageIfKnownUser($chatId, (int) $result['message_id']);
        }
    }

    public function sendPhoto(
        int $chatId,
        string $photoFileId,
        ?string $caption = null,
        ?array $replyMarkup = null,
        ?string $parseMode = 'HTML',
    ): void {
        $payload = [
            'chat_id' => $chatId,
            'photo' => $photoFileId,
        ];
        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }
        if ($parseMode !== null && ($caption !== null && $caption !== '')) {
            $payload['parse_mode'] = $parseMode;
        }

        $json = $this->postJson('sendPhoto', $payload);
        if (! is_array($json) || ! ($json['ok'] ?? false)) {
            return;
        }
        $result = $json['result'] ?? null;
        if (is_array($result) && isset($result['message_id'])) {
            $this->recordOutboundChatMessageIfKnownUser($chatId, (int) $result['message_id']);
        }
    }

    /** @return int|null message_id при успехе */
    public function sendMessageReturnId(int $chatId, string $text, ?array $replyMarkup = null, ?string $parseMode = 'HTML'): ?int
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

        $json = $this->postJson('sendMessage', $payload);
        if (! is_array($json) || ! ($json['ok'] ?? false)) {
            return null;
        }
        $result = $json['result'] ?? null;
        if (! is_array($result) || ! isset($result['message_id'])) {
            return null;
        }
        $mid = (int) $result['message_id'];
        $this->recordOutboundChatMessageIfKnownUser($chatId, $mid);

        return $mid;
    }

    /**
     * Удалить у пользователя сообщения бота, которые мы запомнили (sendMessage).
     * Сообщения пользователя Telegram API не трогает.
     *
     * @param  list<int>  $extraMessageIds  Доп. id (например только что отправленное прощание), если ещё не в БД.
     */
    public function deleteRecordedOutboundMessagesForUser(User $user, array $extraMessageIds = []): void
    {
        $chatId = (int) $user->telegram_id;
        $fromDb = TelegramOutboundMessage::query()
            ->where('user_id', $user->id)
            ->pluck('message_id')
            ->all();
        $ids = array_values(array_unique(array_merge(
            array_map('intval', $fromDb),
            array_map('intval', $extraMessageIds)
        )));
        $this->deleteMessages($chatId, $ids);
    }

    /** @param  list<int>  $messageIds */
    public function deleteMessages(int $chatId, array $messageIds): void
    {
        $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
        if ($messageIds === []) {
            return;
        }

        foreach (array_chunk($messageIds, 100) as $chunk) {
            $json = $this->postJson('deleteMessages', [
                'chat_id' => $chatId,
                'message_ids' => $chunk,
            ]);
            if (! is_array($json) || ! ($json['ok'] ?? false)) {
                foreach ($chunk as $mid) {
                    $this->post('deleteMessage', ['chat_id' => $chatId, 'message_id' => $mid]);
                }
            }
        }
    }

    private function recordOutboundChatMessageIfKnownUser(int $chatId, int $messageId): void
    {
        $user = User::query()->where('telegram_id', $chatId)->first();
        if ($user === null) {
            return;
        }

        try {
            TelegramOutboundMessage::query()->insertOrIgnore([
                'user_id' => $user->id,
                'message_id' => $messageId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('telegram outbound message log failed', ['message' => $e->getMessage()]);
        }

        $overflowIds = TelegramOutboundMessage::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->skip(800)
            ->take(5000)
            ->pluck('id');
        if ($overflowIds->isNotEmpty()) {
            TelegramOutboundMessage::query()->whereIn('id', $overflowIds)->delete();
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null) {
            $payload['text'] = $text;
        }
        $this->post('answerCallbackQuery', $payload);
    }

    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): bool
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
        $json = $this->postJson('editMessageText', $payload);
        if (! is_array($json)) {
            return false;
        }
        if ($json['ok'] ?? false) {
            return true;
        }
        $desc = (string) ($json['description'] ?? '');

        return str_contains($desc, 'message is not modified');
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
                ['text' => '👤 Профиль'],
                ['text' => '✉️ Написать в поддержку'],
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
        $this->postJson($method, $payload);
    }

    /** @return array<string, mixed>|null Полный JSON ответа или null при сетевой/HTTP ошибке. */
    private function postJson(string $method, array $payload): ?array
    {
        try {
            $response = $this->botJsonClient()->post($method, [RequestOptions::JSON => $payload]);
        } catch (Throwable $e) {
            Log::warning('Telegram API exception', [
                'method' => $method,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            Log::warning('Telegram API error', [
                'method' => $method,
                'status' => $status,
                'body' => $body,
            ]);

            return null;
        }

        $json = json_decode($body, true);
        if (! is_array($json)) {
            Log::warning('Telegram API invalid JSON', ['method' => $method, 'body' => $body]);

            return null;
        }

        if (! ($json['ok'] ?? false)) {
            Log::warning('Telegram API ok=false', [
                'method' => $method,
                'description' => $json['description'] ?? null,
                'body' => $body,
            ]);
        }

        return $json;
    }
}
