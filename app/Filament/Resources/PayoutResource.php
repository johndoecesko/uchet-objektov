<?php

namespace App\Filament\Resources;

use App\Enums\PayMethod;
use App\Filament\Resources\PayoutResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Payout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static string | \UnitEnum | null $navigationGroup = 'Люди и деньги';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'выплату';

    protected static ?string $pluralModelLabel = 'Выплаты зарплаты';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function formFields(bool $withEmployee = true): array
    {
        return Fields::full([
            Grid::make(3)->schema(array_values(array_filter([
                Fields::date(),
                $withEmployee ? Fields::employee() : null,
                Fields::money()->required()->minValue(0.01),
            ]))),
            Grid::make(3)->schema([
                Select::make('method')->label('Способ')->options(PayMethod::class)->default(PayMethod::Card)->required(),
                Fields::project(false)->required(false)->helperText('Необязательно: если выплата за конкретный объект'),
            ]),
            Textarea::make('notes')->label('Примечание')->rows(2)->columnSpanFull(),
            Fields::attachments('documents/payouts', 'Чек перевода, расписка (фото или PDF)'),
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
            Fields::moneyColumn(),
            TextColumn::make('method')->label('Способ')->badge(),
            TextColumn::make('project.name')->label('Объект')->placeholder('—')->limit(30),
            TextColumn::make('notes')->label('Примечание')->limit(40)->toggleable(),
            Fields::attachmentsColumn(),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->filters([Fields::periodFilter(), Fields::employeeFilter(), SelectFilter::make('method')->label('Способ')->options(PayMethod::class), Fields::trashedFilter()])
            ->defaultSort('date', 'desc')
            ->recordActions(Fields::recordActions())
            ->toolbarActions(Fields::bulkActions());
    }

    public static function getEloquentQuery(): Builder
    {
        return Fields::withTrashedForOffice(parent::getEloquentQuery())->with(['employee', 'project']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayouts::route('/'),
            'create' => Pages\CreatePayout::route('/create'),
            'edit' => Pages\EditPayout::route('/{record}/edit'),
        ];
    }
}
