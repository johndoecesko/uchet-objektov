<?php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Filament\Support\Fields;
use App\Models\Employee;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class EmployeeStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    public ?Model $record = null;

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
        /** @var Employee $e */
        $e = $this->record;
        $accrued = $e->accrued();
        $paid = $e->paid();
        $debt = $accrued - $paid;
        $adv = $e->advanceBalance();

        return [
            Stat::make('Начислено всего', Money::fmt($accrued))
                ->description('Дней: '.number_format((float) $e->workLogs()->sum('days'), 1, ',', ' ')),
            Stat::make('Выплачено', Money::fmt($paid)),
            Stat::make($debt >= 0 ? 'Должны сотруднику' : 'Переплата сотруднику', Money::fmt(abs($debt)))
                ->color($debt > 0 ? 'warning' : ($debt < 0 ? 'danger' : 'success')),
            Stat::make('Подотчёт на руках', Money::fmt($adv))
                ->description($adv > 0 ? 'Ждём чеки или возврат' : ($adv < 0 ? 'Потратил своих — компенсировать' : 'Закрыт'))
                ->color($adv != 0.0 ? 'warning' : 'success'),
        ];
    }
}
