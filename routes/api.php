<?php

use App\Http\Controllers\MaxWebhookController;
use Illuminate\Support\Facades\Route;

// Вебхук бота MAX (без сессии и CSRF; проверка секрета — в контроллере)
Route::post('/max/webhook', MaxWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('max.webhook');
