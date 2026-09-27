<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkLogResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Employee;
use App\Models\WorkLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkLogResource extends Resource
{
    protected static ?string $model = WorkLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string | \UnitEnum | null $navigationGroup = 'Учёт';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'запись о работе';

    protected static ?string $pluralModelLabel = 'Журнал работ';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function recalc(Get $get, Set $set): void
    {
        $set('amount', round((float) $get('days') * (float) $get('rate'), 2));
    }

    public static function formFields(bool $withProject = true, bool $withEmployee = true): array
    {
        return Fields::full([
            Grid::make(3)->schema(array_values(array_filter([
                Fields::date(),
                $withProject ? Fields::project() : null,
                $withEmployee ? Fields::employee()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $rate = (float) (Employee::find($state)?->day_rate ?? 0);
                        $set('rate', $rate);
                        self::recalc($get, $set);
                    }) : null,
            ]))),
            TextInput::make('work')->label('Какие работы')->maxLength(255)->columnSpanFull(),
            Grid::make(3)->schema([
                TextInput::make('days')->label('Дней (смен)')->numeric()->step(0.5)->minValue(0)->default(1)->required()
                    ->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),
                Fields::money('rate', 'Ставка за день')->default(0)->required()
                    ->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set) => self::recalc($get, $set)),
                Fields::money('amount', 'Начислено')->required()
                    ->helperText('Считается как дни × ставка, можно поправить вручную'),
            ]),
            Textarea::make('notes')->label('Примечание')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formFields());
    }

    public static function tableColumns(bool $withProject = true, bool $withEmployee = true): array
    {
        return array_values(array_filter([
            Fields::dateColumn(),
            $withEmployee ? TextColumn::make('employee.name')->label('Сотрудник')->searchable() : null,
            $withProject ? TextColumn::make('project.name')->label('Объект')->searchable()->limit(30) : null,
            TextColumn::make('work')->label('Работы')->limit(40)->searchable(),
            TextColumn::make('days')->label('Дней')->numeric(decimalPlaces: 1, locale: 'ru')->alignEnd()
                ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Дней')->numeric(decimalPlaces: 1, locale: 'ru')),
            Fields::moneyColumn('rate', 'Ставка', false)->toggleable(isToggledHiddenByDefault: true),
            Fields::moneyColumn('amount', 'Начислено'),
            TextColumn::make('creator.name')->label('Внёс')->toggleable(isToggledHiddenByDefault: true),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->filters([Fields::periodFilter(), Fields::projectFilter(), Fields::employeeFilter()])
            ->defaultSort('date', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['project', 'employee', 'creator']);

        return Fields::isOffice() ? $q : $q->where('created_by', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkLogs::route('/'),
            'create' => Pages\CreateWorkLog::route('/create'),
            'edit' => Pages\EditWorkLog::route('/{record}/edit'),
        ];
    }
}
