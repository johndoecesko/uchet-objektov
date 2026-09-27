<?php

namespace App\Filament\Resources;

use App\Enums\PaymentSource;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Expense;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string | \UnitEnum | null $navigationGroup = 'Учёт';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'расход';

    protected static ?string $pluralModelLabel = 'Расходы';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function formFields(bool $withProject = true): array
    {
        return Fields::full([
            Grid::make(3)->schema([
                Fields::date(),
                ...($withProject ? [Fields::project()->columnSpan(2)] : []),
            ]),
            Grid::make(3)->schema([
                Fields::category(),
                Fields::money()->required()->minValue(0.01),
                TextInput::make('supplier')->label('Поставщик / кому')->maxLength(255),
            ]),
            Grid::make(3)->schema([
                Select::make('payment_source')
                    ->label('Чем оплачено')
                    ->options(PaymentSource::class)
                    ->default(PaymentSource::Bank)
                    ->required()
                    ->live(),
                Fields::employee('employee_id', 'Чей подотчёт')
                    ->visible(fn (Get $get) => self::isAdvance($get('payment_source')))
                    ->required(fn (Get $get) => self::isAdvance($get('payment_source'))),
                TextInput::make('document')->label('Документ (чек, счёт, УПД)')->maxLength(255),
            ]),
            Textarea::make('description')->label('Что купили / комментарий')->rows(2)->columnSpanFull(),
            FileUpload::make('attachments')
                ->label('Фото чеков и документов')
                ->multiple()
                ->disk('local')
                ->directory('receipts')
                ->visibility('private')
                ->acceptedFileTypes(['image/*', 'application/pdf'])
                ->maxSize(10240)
                ->openable()
                ->downloadable()
                ->columnSpanFull(),
        ]);
    }

    public static function isAdvance(mixed $state): bool
    {
        return $state === PaymentSource::Advance || $state === PaymentSource::Advance->value;
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
            TextColumn::make('category.name')->label('Статья')->badge()->color('gray'),
            TextColumn::make('description')->label('Что')->limit(40)->searchable()
                ->description(fn (Expense $r) => $r->supplier),
            Fields::moneyColumn(),
            TextColumn::make('payment_source')->label('Оплата')->badge()
                ->description(fn (Expense $r) => $r->employee?->name)
                ->toggleable(),
            TextColumn::make('attachments')->label('Чеки')
                ->state(fn (Expense $r) => count($r->attachments ?? []) ?: null)
                ->placeholder('—')
                ->icon('heroicon-o-paper-clip')
                ->toggleable(),
            TextColumn::make('creator.name')->label('Внёс')->toggleable(isToggledHiddenByDefault: true),
        ]));
    }

    public static function tableFilters(bool $withProject = true): array
    {
        return array_values(array_filter([
            Fields::periodFilter(),
            $withProject ? Fields::projectFilter() : null,
            Fields::categoryFilter(),
            SelectFilter::make('payment_source')->label('Чем оплачено')->options(PaymentSource::class),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->filters(static::tableFilters())
            ->defaultSort('date', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['project', 'category', 'employee', 'creator']);

        return Fields::isOffice() ? $q : $q->where('created_by', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
