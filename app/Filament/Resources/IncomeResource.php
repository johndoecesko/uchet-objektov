<?php

namespace App\Filament\Resources;

use App\Enums\IncomeType;
use App\Enums\PayMethod;
use App\Filament\Resources\IncomeResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Income;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IncomeResource extends Resource
{
    protected static ?string $model = Income::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string | \UnitEnum | null $navigationGroup = 'Учёт';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'поступление';

    protected static ?string $pluralModelLabel = 'Поступления от заказчиков';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function formFields(bool $withProject = true): array
    {
        return Fields::full([
            Grid::make(3)->schema(array_values(array_filter([
                Fields::date(),
                $withProject ? Fields::project(false)->columnSpan(2) : null,
            ]))),
            Grid::make(3)->schema([
                Fields::money()->required()->minValue(0.01),
                Select::make('type')->label('Тип')->options(IncomeType::class)->default(IncomeType::Advance)->required(),
                Select::make('method')->label('Способ')->options(PayMethod::class)->default(PayMethod::Bank)->required(),
            ]),
            TextInput::make('document')->label('Документ (п/п, счёт, акт)')->maxLength(255),
            Textarea::make('notes')->label('Примечание')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formFields());
    }

    public static function tableColumns(bool $withProject = true): array
    {
        return array_values(array_filter([
            Fields::dateColumn(),
            $withProject ? TextColumn::make('project.name')->label('Объект')->searchable()->limit(30) : null,
            TextColumn::make('type')->label('Тип')->badge(),
            Fields::moneyColumn(),
            TextColumn::make('method')->label('Способ')->badge()->color('gray')->toggleable(),
            TextColumn::make('document')->label('Документ')->toggleable(),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->filters([Fields::periodFilter(), Fields::projectFilter(), SelectFilter::make('type')->label('Тип')->options(IncomeType::class)])
            ->defaultSort('date', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['project']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIncomes::route('/'),
            'create' => Pages\CreateIncome::route('/create'),
            'edit' => Pages\EditIncome::route('/{record}/edit'),
        ];
    }
}
