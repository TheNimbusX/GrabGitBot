<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    // Исходящий HTTP(S)/SOCKS-прокси до api.telegram.org (например http://user:pass@host:8118 или socks5://127.0.0.1:1080)
    'http_proxy' => env('TELEGRAM_HTTP_PROXY'),
];
