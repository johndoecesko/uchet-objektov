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

    // Бот в MAX для чеков (https://dev.max.ru). Токен — от @MasterBot, секрет вебхука — любая строка A-Z a-z 0-9 _ -
    'max' => [
        'token' => env('MAX_BOT_TOKEN'),
        'webhook_secret' => env('MAX_WEBHOOK_SECRET'),
        'url' => env('MAX_API_URL', 'https://platform-api2.max.ru'),
        // false — событие обрабатывается сразу после ответа MAX; true — через очередь (воркер раз в минуту)
        'queue' => (bool) env('MAX_QUEUE', false),
    ],

];
