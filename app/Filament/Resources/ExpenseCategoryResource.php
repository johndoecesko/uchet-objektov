<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'статью затрат';

    protected static ?string $pluralModelLabel = 'Статьи затрат';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Название')->required()->maxLength(255),
            TextInput::make('sort')->label('Порядок')->numeric()->default(100),
            Toggle::make('is_labor')->label('Сюда идёт зарплата из журнала работ')
                ->helperText('Должна быть ровно одна такая статья'),
            Toggle::make('is_tax')->label('Налоги: считается как % от договора объекта')
                ->helperText('Процент задаётся в карточке объекта. Вручную в эту статью расходы не вносятся'),
            Toggle::make('is_active')->label('Активна')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort')->label('№')->sortable(),
                TextColumn::make('name')->label('Название')->searchable(),
                IconColumn::make('is_labor')->label('ФОТ')->boolean(),
                IconColumn::make('is_tax')->label('Налоги')->boolean(),
                IconColumn::make('is_active')->label('Активна')->boolean(),
                TextColumn::make('expenses_count')->label('Расходов')->counts('expenses'),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseCategories::route('/'),
            'create' => Pages\CreateExpenseCategory::route('/create'),
            'edit' => Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}
