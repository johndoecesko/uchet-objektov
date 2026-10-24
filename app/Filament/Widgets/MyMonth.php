<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Fields;
use App\Models\Expense;
use App\Models\WorkLog;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Для прораба: что он внёс в этом месяце */
class MyMonth extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth()->check() && ! Fields::isOffice();
    }

    protected function getColumns(): int | array | null
    {
        return 2;
    }

    protected function getStats(): array
    {
        $from = now()->startOfMonth()->toDateString();
        $uid = auth()->id();

        return [
            Stat::make('Мои расходы за месяц', Money::fmt((float) Expense::where('created_by', $uid)->whereDate('date', '>=', $from)->sum('amount'))),
            Stat::make('Записей в журнале за месяц', (string) WorkLog::where('created_by', $uid)->whereDate('date', '>=', $from)->count()),
        ];
    }
}
