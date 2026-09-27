<?php

namespace App\Filament\Support;

use App\Enums\ProjectStatus;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/** Общие поля и колонки, чтобы формы и таблицы были одинаковыми во всех разделах */
class Fields
{
    public static function isOffice(): bool
    {
        return (bool) auth()->user()?->isOffice();
    }

    /** Растянуть элементы формы на всю ширину (в Filament 4 блоки по умолчанию занимают одну колонку из двух) */
    public static function full(array $components): array
    {
        return array_map(fn ($c) => $c->columnSpanFull(), array_values(array_filter($components)));
    }

    public static function date(string $name = 'date', string $label = 'Дата'): DatePicker
    {
        return DatePicker::make($name)
            ->label($label)
            ->native(false)
            ->displayFormat('d.m.Y')
            ->default(now())
            ->required();
    }

    public static function money(string $name = 'amount', string $label = 'Сумма'): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->inputMode('decimal')
            ->step(0.01)
            ->minValue(0)
            ->suffix('₽');
    }

    public static function project(bool $onlyOpen = true): Select
    {
        return Select::make('project_id')
            ->label('Объект')
            ->relationship(
                'project',
                'name',
                fn (Builder $query) => $onlyOpen
                    ? $query->whereIn('status', [ProjectStatus::Planned->value, ProjectStatus::Active->value])->orderBy('name')
                    : $query->orderBy('name'),
            )
            ->searchable()
            ->preload()
            ->required();
    }

    public static function employee(string $name = 'employee_id', string $label = 'Сотрудник'): Select
    {
        return Select::make($name)
            ->label($label)
            ->relationship('employee', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
            ->searchable()
            ->preload()
            ->required();
    }

    public static function category(): Select
    {
        return Select::make('expense_category_id')
            ->label('Статья затрат')
            ->relationship('category', 'name', fn (Builder $query) => $query->where('is_active', true)->where('is_labor', false)->where('is_tax', false)->orderBy('sort'))
            ->preload()
            ->required();
    }

    public static function dateColumn(string $name = 'date', string $label = 'Дата'): TextColumn
    {
        return TextColumn::make($name)->label($label)->date('d.m.Y')->sortable();
    }

    public static function moneyColumn(string $name = 'amount', string $label = 'Сумма', bool $sum = true): TextColumn
    {
        $col = TextColumn::make($name)
            ->label($label)
            ->money('RUB', locale: 'ru')
            ->alignEnd()
            ->sortable();

        return $sum ? $col->summarize(Sum::make()->label('Итого')->money('RUB', locale: 'ru')) : $col;
    }

    public static function projectFilter(): SelectFilter
    {
        return SelectFilter::make('project_id')
            ->label('Объект')
            ->options(fn () => Project::query()->orderBy('name')->pluck('name', 'id'))
            ->searchable();
    }

    public static function employeeFilter(): SelectFilter
    {
        return SelectFilter::make('employee_id')
            ->label('Сотрудник')
            ->options(fn () => Employee::query()->orderBy('name')->pluck('name', 'id'))
            ->searchable();
    }

    public static function categoryFilter(): SelectFilter
    {
        return SelectFilter::make('expense_category_id')
            ->label('Статья')
            ->options(fn () => ExpenseCategory::query()->orderBy('sort')->pluck('name', 'id'));
    }

    public static function periodFilter(string $column = 'date'): Filter
    {
        return Filter::make('period')
            ->label('Период')
            ->schema([
                DatePicker::make('from')->label('С')->native(false)->displayFormat('d.m.Y'),
                DatePicker::make('until')->label('По')->native(false)->displayFormat('d.m.Y'),
            ])
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate($column, '>=', $d))
                ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate($column, '<=', $d)))
            ->indicateUsing(function (array $data): array {
                $out = [];
                if ($data['from'] ?? null) {
                    $out[] = 'с '.\Illuminate\Support\Carbon::parse($data['from'])->format('d.m.Y');
                }
                if ($data['until'] ?? null) {
                    $out[] = 'по '.\Illuminate\Support\Carbon::parse($data['until'])->format('d.m.Y');
                }

                return $out;
            });
    }
}
