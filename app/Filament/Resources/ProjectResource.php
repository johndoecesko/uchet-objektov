<?php

namespace App\Filament\Resources;

use App\Enums\ProjectStatus;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Filament\Support\Fields;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Support\DaData;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | \UnitEnum | null $navigationGroup = 'Учёт';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'объект';

    protected static ?string $pluralModelLabel = 'Объекты';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Объект и договор')->columnSpanFull()->schema([
                Grid::make(3)->schema([
                    TextInput::make('name')->label('Название объекта')->required()->maxLength(255)->columnSpan(2),
                    Select::make('status')->label('Статус')->options(ProjectStatus::class)->default(ProjectStatus::Active)->required(),
                ]),
                Select::make('customer_lookup')
                    ->label('Найти заказчика по ИНН или названию')
                    ->placeholder('Начните вводить ИНН или название')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => DaData::parties($search))
                    ->getOptionLabelUsing(fn ($value): ?string => DaData::party($value)['name'] ?? $value)
                    ->searchPrompt('ИНН, ОГРН или название организации')
                    ->noSearchResultsMessage('Ничего не найдено')
                    ->searchingMessage('Ищу в DaData…')
                    ->helperText('Заполнит название, ИНН, КПП, ОГРН и юридический адрес — их можно поправить')
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($p = DaData::party($state)) {
                            $set('customer', $p['name']);
                            $set('customer_inn', $p['inn']);
                            $set('customer_kpp', $p['kpp']);
                            $set('customer_ogrn', $p['ogrn']);
                            $set('customer_address', $p['address']);
                        }
                    })
                    ->dehydrated(false)
                    ->visible(fn () => DaData::enabled())
                    ->columnSpanFull(),
                Grid::make(4)->schema([
                    TextInput::make('customer')->label('Заказчик')->maxLength(255)->columnSpan(2),
                    TextInput::make('customer_inn')->label('ИНН')->maxLength(12),
                    TextInput::make('customer_kpp')->label('КПП')->maxLength(9),
                ]),
                Grid::make(4)->schema([
                    TextInput::make('customer_ogrn')->label('ОГРН')->maxLength(15),
                    TextInput::make('customer_address')->label('Юридический адрес заказчика')->maxLength(255)->columnSpan(3),
                ]),
                Grid::make(4)->schema([
                    Select::make('address_lookup')
                        ->label('Найти адрес объекта')
                        ->placeholder('Город, улица, дом')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => DaData::addresses($search))
                        ->getOptionLabelUsing(fn ($value): ?string => $value)
                        ->noSearchResultsMessage('Ничего не найдено')
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set) => $state ? $set('address', $state) : null)
                        ->dehydrated(false)
                        ->visible(fn () => DaData::enabled())
                        ->columnSpan(2),
                    TextInput::make('address')->label('Адрес объекта')->maxLength(255)
                        ->helperText('Можно вписать вручную, если адреса нет в справочнике')
                        ->columnSpan(fn () => DaData::enabled() ? 2 : 4),
                ]),
                Grid::make(3)->schema([
                    TextInput::make('contract_number')->label('№ договора / контракта')->maxLength(255),
                    Fields::date('contract_date', 'Дата договора')->required(false)->default(null),
                    Fields::money('contract_amount', 'Сумма договора')->default(0)->required()
                        ->live(onBlur: true),
                ]),
                Grid::make(3)->schema([
                    TextInput::make('tax_percent')->label('Налоги, % от договора')
                        ->numeric()->inputMode('decimal')->step(0.01)->minValue(0)->maxValue(100)
                        ->default(0)->required()->suffix('%')
                        ->datalist(['6', '7', '5', '15', '20', '22'])
                        ->live(onBlur: true)
                        ->helperText(fn (Get $get) => 'Будет учтено в затратах: '
                            .Money::fmt(Project::taxFor((float) $get('contract_amount'), (float) $get('tax_percent')))),
                ]),
                Grid::make(3)->schema([
                    Fields::date('start_date', 'Начало работ')->required(false)->default(null),
                    Fields::date('end_date', 'Окончание (план)')->required(false)->default(null),
                ]),
                Textarea::make('notes')->label('Примечание')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Бюджет по статьям (план)')
                ->description('Сколько планируете потратить по каждой статье — из сметы или КП. Можно заполнить позже.')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Repeater::make('budgets')
                        ->hiddenLabel()
                        ->relationship()
                        ->schema([
                            Select::make('expense_category_id')->label('Статья')
                                ->options(fn () => ExpenseCategory::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id'))
                                ->required()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            Fields::money('amount', 'План')->required(),
                        ])
                        ->columns(2)
                        ->addActionLabel('Добавить статью')
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Grid::make(4)->schema([
                    TextEntry::make('customer')->label('Заказчик')->placeholder('—'),
                    TextEntry::make('customer_inn')->label('ИНН / КПП')->placeholder('—')
                        ->formatStateUsing(fn ($state, Project $record) => $state.($record->customer_kpp ? ' / '.$record->customer_kpp : '')),
                    TextEntry::make('contract_number')->label('Договор')->placeholder('—')
                        ->formatStateUsing(fn (Project $record, $state) => $state.($record->contract_date ? ' от '.$record->contract_date->format('d.m.Y') : '')),
                    TextEntry::make('status')->label('Статус')->badge(),
                    TextEntry::make('address')->label('Адрес')->placeholder('—'),
                    TextEntry::make('start_date')->label('Начало')->date('d.m.Y')->placeholder('—'),
                    TextEntry::make('end_date')->label('Окончание')->date('d.m.Y')->placeholder('—'),
                    TextEntry::make('notes')->label('Примечание')->placeholder('—')->columnSpan(2),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum('expenses', 'amount')
                ->withSum('workLogs', 'amount')
                ->withSum('incomes', 'amount')
                ->withSum('budgets', 'amount'))
            ->columns([
                TextColumn::make('name')->label('Объект')->searchable(['name', 'customer', 'customer_inn', 'address'])->sortable()->wrap()
                    ->description(fn (Project $r) => trim($r->customer.($r->customer_inn ? ' · ИНН '.$r->customer_inn : ''))),
                TextColumn::make('status')->label('Статус')->badge()->sortable(),
                TextColumn::make('contract_amount')->label('Договор')->money('RUB', locale: 'ru')->alignEnd()->sortable()
                    ->visible(fn () => Fields::isOffice()),
                TextColumn::make('incomes_sum_amount')->label('Получено')->money('RUB', locale: 'ru')->alignEnd()->placeholder('0 ₽')
                    ->visible(fn () => Fields::isOffice()),
                TextColumn::make('cost')->label('Затраты')->alignEnd()
                    ->state(fn (Project $r) => static::rowCost($r))
                    ->formatStateUsing(fn ($state) => Money::fmt($state))
                    ->visible(fn () => Fields::isOffice()),
                TextColumn::make('budget_use')->label('Освоение бюджета')->alignEnd()
                    ->state(fn (Project $r) => (float) $r->budgets_sum_amount > 0
                        ? round(static::rowCost($r) / (float) $r->budgets_sum_amount * 100)
                        : null)
                    ->formatStateUsing(fn ($state) => $state.'%')
                    ->placeholder('бюджет не задан')
                    ->badge()
                    ->color(fn ($state) => Money::budgetColor($state))
                    ->visible(fn () => Fields::isOffice()),
                TextColumn::make('forecast')->label('Прогноз прибыли')->alignEnd()
                    ->state(fn (Project $r) => $r->forecastProfit())
                    ->formatStateUsing(fn ($state, Project $r) => Money::fmt($state).' · '.Money::pct((float) $state, (float) $r->contract_amount))
                    ->color(fn ($state) => $state < 0 ? 'danger' : null)
                    ->visible(fn () => Fields::isOffice()),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(ProjectStatus::class)
                    ->default(ProjectStatus::Active->value),
            ])
            ->defaultSort('name')
            ->recordUrl(fn (Project $r) => static::getUrl('view', ['record' => $r]))
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    /** Затраты строки списка: расходы + работа + налоги (суммы из withSum) */
    public static function rowCost(Project $r): float
    {
        return (float) $r->expenses_sum_amount + (float) $r->work_logs_sum_amount + $r->taxTotal();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ExpensesRelationManager::class,
            RelationManagers\WorkLogsRelationManager::class,
            RelationManagers\IncomesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
