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

// Очередь без постоянного воркера (виртуальный хостинг): разбираем раз в минуту.
// Срок блокировки 5 минут — если процесс убьют, следующий запуск не будет ждать сутки.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()->withoutOverlapping(5);

Schedule::command('app:backup')->dailyAt('03:10')->withoutOverlapping(60);
