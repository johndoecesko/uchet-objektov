<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Подсказки DaData: организации по ИНН/названию и адреса (ключ API — только на сервере, в .env)
    'dadata' => [
        'token' => env('DADATA_TOKEN'),
        'url' => env('DADATA_URL', 'https://suggestions.dadata.ru/suggestions/api/4_1/rs'),
    ],

];
