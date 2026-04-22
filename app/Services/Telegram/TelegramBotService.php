<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    public function apiUrl(string $method): string
    {
        $token = (string) config('telegram.bot_token');

        return 'https://api.telegram.org/bot'.$token.'/'.$method;
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

    private function post(string $method, array $payload): void
    {
        $response = Http::asJson()->post($this->apiUrl($method), $payload);

        if (! $response->successful()) {
            Log::warning('Telegram API error', [
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
