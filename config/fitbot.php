<?php

return [

    'admin_password' => env('FITBOT_ADMIN_PASSWORD'),
    'club_invite_url' => env('FITBOT_CLUB_INVITE_URL', 'https://t.me/+3o_GirDWRE9lNDRi'),
    'club_chat_id' => env('FITBOT_CLUB_CHAT_ID'),
    'club_free_slots' => (int) env('FITBOT_CLUB_FREE_SLOTS', 30),
    'bot_username' => env('FITBOT_BOT_USERNAME'),

];
