<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Filament\Support\Fields;
use App\Models\Project;
use App\Support\Attention;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class ProjectStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    public ?Model $record = null;

    protected ?string $pollingInterval = null;

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
        /** @var Project $p */
        $p = $this->record;
        $contract = (float) $p->contract_amount;
        $received = $p->receivedTotal();
        $materials = $p->expensesTotal();
        $labor = $p->laborTotal();
        $tax = $p->taxTotal();
        $cost = $materials + $labor + $tax;
        $budget = $p->budgetTotal();
        $margin = $contract - $cost;
        $forecast = $p->forecastProfit();

        return [
            Stat::make('Договор', Money::fmt($contract))
                ->description('Получено '.Money::fmt($received).' · долг заказчика '.Money::fmt($contract - $received)),
            Stat::make('Затраты факт', Money::fmt($cost))
                ->description('Расходы '.Money::fmt($materials).' · работа '.Money::fmt($labor)
                    .($tax > 0 ? ' · налоги '.Money::fmt($tax).' ('.rtrim(rtrim(number_format((float) $p->tax_percent, 2, ',', ''), '0'), ',').'%)' : ''))
                ->color($budget > 0 && $cost > $budget ? 'danger' : 'gray'),
            Stat::make('Бюджет (план)', $budget > 0 ? Money::fmt($budget) : 'не задан')
                ->description($budget > 0 ? 'Израсходовано '.Money::pct($cost, $budget).' · остаток '.Money::fmt($budget - $cost) : 'Заполните в редактировании объекта')
                ->color($budget > 0 && $cost > $budget ? 'danger' : 'gray'),
            Stat::make('Прогноз прибыли', Money::fmt($forecast))
                ->description('Рентабельность '.Money::pct($forecast, $contract)
                    .' · сейчас '.Money::fmt($margin).' · по деньгам '.Money::fmt($received - $cost, true))
                ->color($forecast < 0 ? 'danger' : ($contract > 0 && $forecast / $contract * 100 < Attention::LOW_MARGIN ? 'warning' : 'success')),
        ];
    }
}
