<?php

/*
 * Публичное демо: кнопка «Посмотреть демо» на главной → POST /demo → вход в общую демо-организацию только для чтения.
 * Данные пересоздаёт app:demo-reset (ночью). Устройство — App\Support\Demo\*, HANDOFF.md, раздел «Демо».
 * Аварийное отключение — DEMO_ENABLED=false (+ artisan optimize): кнопки нет, POST /demo → 404, демо-сессии разлогиниваются.
 */
return [
    'enabled' => (bool) env('DEMO_ENABLED', false),

    // Яндекс SmartCaptcha перед входом в демо (по умолчанию выключена — вход одной кнопкой)
    'captcha' => (bool) env('DEMO_CAPTCHA', false),

    // Адрес демо-организации: /app/demo. Константа — зарезервирован в Organization::RESERVED_SLUGS
    'slug' => 'demo',

    // Выгрузки Excel из демо: не больше N в час на сессию и IP
    'export_per_hour' => 10,

    // Демо-сессии без активности дольше N часов удаляются (Demo::pruneSessions, раз в час)
    'session_idle_hours' => 3,
];
