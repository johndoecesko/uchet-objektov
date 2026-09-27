<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Filament\Support\Fields;
use App\Models\Project;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

class ProjectPlanFact extends TableWidget
{
    protected static bool $isLazy = false;

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Fields::isOffice();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('План / факт по статьям затрат')
            ->records(function (): array {
                /** @var Project $p */
                $p = $this->record;
                $rows = [];
                $tp = $tf = 0.0;
                foreach ($p->planFact() as $r) {
                    $rows['c'.$r['category_id']] = $r;
                    $tp += $r['plan'];
                    $tf += $r['fact'];
                }
                if ($rows) {
                    $rows['total'] = ['category_id' => 0, 'name' => 'Итого', 'plan' => $tp, 'fact' => $tf, 'rest' => $tp - $tf, 'is_tax' => false];
                }

                return $rows;
            })
            ->columns([
                TextColumn::make('name')->label('Статья')
                    ->weight(fn (array $record) => $record['category_id'] === 0 ? 'bold' : null),
                TextColumn::make('plan')->label('План')->alignEnd()
                    ->formatStateUsing(fn ($state) => $state > 0 ? Money::fmt($state) : '—'),
                TextColumn::make('fact')->label('Факт')->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::fmt($state)),
                TextColumn::make('rest')->label('Остаток')->alignEnd()
                    ->state(fn (array $record) => $record['plan'] > 0 ? $record['rest'] : null)
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => Money::fmt($state))
                    ->color(fn ($state, array $record) => ! $record['is_tax'] && $state !== null && $state < 0 ? 'danger' : null),
                TextColumn::make('used')->label('Освоено')->alignEnd()
                    ->state(fn (array $record) => $record['plan'] > 0 ? round($record['fact'] / $record['plan'] * 100) : null)
                    ->formatStateUsing(fn ($state) => $state.'%')
                    ->badge()
                    ->color(fn ($state, array $record) => $record['is_tax'] ? 'gray' : Money::budgetColor($state))
                    ->placeholder('нет плана'),
            ])
            ->emptyStateHeading('Пока нет ни расходов, ни бюджета')
            ->paginated(false);
    }
}
