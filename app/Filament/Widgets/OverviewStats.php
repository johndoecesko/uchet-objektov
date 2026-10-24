<?php

namespace App\Filament\Widgets;

use App\Enums\AdvanceType;
use App\Enums\PaymentSource;
use App\Enums\ProjectStatus;
use App\Filament\Support\Fields;
use App\Models\Advance;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payout;
use App\Models\Project;
use App\Models\WorkLog;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Fields::isOffice();
    }

    protected function getColumns(): int | array | null
    {
        return 4;
    }

    protected function getStats(): array
    {
        $from = now()->startOfMonth()->toDateString();
        $month = now()->translatedFormat('F');

        $received = (float) Income::whereDate('date', '>=', $from)->sum('amount');
        $spent = (float) Expense::whereDate('date', '>=', $from)->sum('amount')
            + (float) WorkLog::whereDate('date', '>=', $from)->sum('amount');
        $salaryDebt = (float) WorkLog::sum('amount') - (float) Payout::sum('amount');
        $advance = (float) Advance::where('type', AdvanceType::Issue->value)->sum('amount')
            - (float) Advance::where('type', AdvanceType::Return->value)->sum('amount')
            - (float) Expense::where('payment_source', PaymentSource::Advance->value)->sum('amount');

        $active = Project::where('status', ProjectStatus::Active->value)->count();

        return [
            Stat::make('Объектов в работе', (string) $active),
            Stat::make("Поступило в этом месяце ({$month})", Money::fmt($received)),
            Stat::make("Затраты в этом месяце ({$month})", Money::fmt($spent))
                ->description('Расходы + начисленная работа'),
            Stat::make('Долг по зарплате / подотчёт', Money::fmt($salaryDebt))
                ->description('Подотчёт на руках: '.Money::fmt($advance))
                ->color($salaryDebt > 0 ? 'warning' : 'success'),
        ];
    }
}
