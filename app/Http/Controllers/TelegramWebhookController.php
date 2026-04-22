<?php

namespace App\Http\Controllers;

use App\Services\FitBot\FitBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, FitBotService $fitBot): Response
    {
        $secret = config('telegram.webhook_secret');
        if (is_string($secret) && $secret !== '') {
            if ($request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
                return response('Forbidden', 403);
            }
        }

        $payload = $request->all();
        if ($payload !== []) {
            $fitBot->handleUpdate($payload);
        }

        return response('OK', 200);
    }
}
