<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Планировщик. На сервере — одна строка в crontab:
 * * * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
 */

// Метка «планировщик жив» — время последнего запуска видно по дате файла
Schedule::call(fn () => touch(storage_path('framework/schedule-heartbeat')))
    ->name('heartbeat')->everyMinute();

// Очередь без постоянного воркера (виртуальный хостинг): воркер живёт ~55 с и проверяет очередь каждые 3 с,
// каждую минуту планировщик запускает новый — письма (подтверждение, сброс пароля) уходят за секунды, а не за минуту.
// В фоне — чтобы не задерживать остальные задачи планировщика. Блокировка 5 минут — если процесс убьют,
// следующий запуск не будет ждать сутки.
Schedule::command('queue:work --max-time=55 --sleep=3 --tries=3')
    ->everyMinute()->withoutOverlapping(5)->runInBackground();

Schedule::command('app:backup')->dailyAt('03:10')->withoutOverlapping(60);

// История изменений: храним год (config/activitylog.php), чистим раз в неделю
Schedule::command('activitylog:clean --force')->weeklyOn(0, '03:40');

// Публичное демо: данные пересоздаются каждую ночь (даты «свежие», правок гостей нет — демо только для чтения),
// старые демо-сессии чистятся раз в час. Выключено (DEMO_ENABLED) — не идёт.
Schedule::command('app:demo-reset')->dailyAt('04:30')->timezone('Europe/Moscow')
    ->withoutOverlapping(30)->when(fn () => (bool) config('demo.enabled'));
Schedule::call(fn () => \App\Support\Demo\Demo::pruneSessions())->name('demo-sessions')->hourly();
