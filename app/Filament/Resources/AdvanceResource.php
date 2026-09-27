<?php

namespace App\Filament\Resources;

use App\Enums\AdvanceType;
use App\Enums\PayMethod;
use App\Filament\Resources\AdvanceResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Advance;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdvanceResource extends Resource
{
    protected static ?string $model = Advance::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wallet';

    protected static string | \UnitEnum | null $navigationGroup = 'Люди и деньги';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'операцию подотчёта';

    protected static ?string $pluralModelLabel = 'Подотчёт';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function formFields(bool $withEmployee = true): array
    {
        return Fields::full([
            Grid::make(3)->schema(array_values(array_filter([
                Fields::date(),
                $withEmployee ? Fields::employee() : null,
                Select::make('type')->label('Операция')->options(AdvanceType::class)->default(AdvanceType::Issue)->required(),
            ]))),
            Grid::make(3)->schema([
                Fields::money()->required()->minValue(0.01),
                Select::make('method')->label('Способ')->options(PayMethod::class)->default(PayMethod::Cash)->required(),
            ]),
            Textarea::make('notes')->label('Примечание')->rows(2)->columnSpanFull()
                ->helperText('Расходы из подотчёта вносятся в «Расходы» с оплатой «Из подотчёта сотрудника» — они уменьшают остаток.'),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formFields());
    }

    public static function tableColumns(bool $withEmployee = true): array
    {
        return array_values(array_filter([
            Fields::dateColumn(),
            $withEmployee ? TextColumn::make('employee.name')->label('Сотрудник')->searchable() : null,
            TextColumn::make('type')->label('Операция')->badge()
                ->color(fn (Advance $r) => $r->type === AdvanceType::Issue ? 'warning' : 'success'),
            Fields::moneyColumn('amount', 'Сумма', false),
            TextColumn::make('method')->label('Способ')->badge()->color('gray'),
            TextColumn::make('notes')->label('Примечание')->limit(40)->toggleable(),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->filters([Fields::periodFilter(), Fields::employeeFilter(), SelectFilter::make('type')->label('Операция')->options(AdvanceType::class)])
            ->defaultSort('date', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdvances::route('/'),
            'create' => Pages\CreateAdvance::route('/create'),
            'edit' => Pages\EditAdvance::route('/{record}/edit'),
        ];
    }
}
