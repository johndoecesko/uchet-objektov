<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStatus;
use App\Filament\Resources\ProjectResource;
use App\Filament\Support\Fields;
use App\Models\Project;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ActiveProjects extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Fields::isOffice();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Объекты в работе')
            ->query(fn () => Project::query()
                ->where('status', ProjectStatus::Active->value)
                ->withSum('expenses', 'amount')
                ->withSum('workLogs', 'amount')
                ->withSum('incomes', 'amount')
                ->withSum('budgets', 'amount'))
            ->columns([
                TextColumn::make('name')->label('Объект')->wrap()->description(fn (Project $r) => $r->customer),
                TextColumn::make('contract_amount')->label('Договор')->money('RUB', locale: 'ru')->alignEnd(),
                TextColumn::make('incomes_sum_amount')->label('Получено')->money('RUB', locale: 'ru')->alignEnd()->placeholder('0 ₽'),
                TextColumn::make('cost')->label('Затраты')->alignEnd()
                    ->state(fn (Project $r) => ProjectResource::rowCost($r))
                    ->formatStateUsing(fn ($state, Project $r) => Money::fmt($state)
                        .((float) $r->budgets_sum_amount > 0 ? ' · '.Money::pct((float) $state, (float) $r->budgets_sum_amount).' бюджета' : ''))
                    ->color(fn ($state, Project $r) => (float) $r->budgets_sum_amount > 0 && $state > (float) $r->budgets_sum_amount ? 'danger' : null),
                TextColumn::make('forecast')->label('Прогноз прибыли')->alignEnd()
                    ->state(fn (Project $r) => $r->forecastProfit())
                    ->formatStateUsing(fn ($state, Project $r) => Money::fmt($state).' · '.Money::pct((float) $state, (float) $r->contract_amount))
                    ->color(fn ($state) => $state < 0 ? 'danger' : null),
            ])
            ->recordUrl(fn (Project $r) => ProjectResource::getUrl('view', ['record' => $r]))
            ->defaultSort('name')
            ->paginated([10, 25, 50]);
    }
}
