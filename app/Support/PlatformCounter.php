<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Счётчики платформы по дням (таблица platform_counters): demo.enter, demo.signup_click, demo.registered.
 * Внутри учёта Метрики нет — такие события считаем сами. Сбой счётчика не мешает основному действию.
 */
class PlatformCounter
{
    public static function hit(string $key, int $by = 1): void
    {
        try {
            $date = now()->toDateString();
            DB::table('platform_counters')->upsert(
                [['date' => $date, 'key' => $key, 'count' => $by]],
                ['date', 'key'],
                ['count' => DB::raw(DB::getQueryGrammar()->wrap('count').' + '.(int) $by)],
            );
        } catch (Throwable $e) {
            Log::warning('PlatformCounter: '.$e->getMessage(), ['key' => $key]);
        }
    }

    /** Сумма за последние $days дней, включая сегодня */
    public static function sum(string $key, int $days = 7): int
    {
        return (int) DB::table('platform_counters')
            ->where('key', $key)
            ->where('date', '>=', now()->subDays($days - 1)->toDateString())
            ->sum('count');
    }
}
