<?php

namespace App\Filament\Resources;

use App\Enums\AdvanceType;
use App\Enums\PaymentSource;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Filament\Support\Fields;
use App\Models\Employee;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Люди и деньги';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'сотрудника';

    protected static ?string $pluralModelLabel = 'Сотрудники';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Grid::make(3)->schema([
                    TextInput::make('name')->label('ФИО')->required()->maxLength(255),
                    TextInput::make('phone')->label('Телефон')->tel()->maxLength(50),
                    TextInput::make('position')->label('Должность / специальность')->maxLength(255),
                ]),
                Grid::make(3)->schema([
                    Fields::money('day_rate', 'Дневная ставка')->default(0)->required()
                        ->helperText('Подставляется в журнал работ'),
                    TextInput::make('card_percent')->label('% на карту')->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
                    Toggle::make('is_active')->label('Работает')->default(true)->inline(false),
                ]),
                Select::make('user_id')->label('Учётная запись для входа')
                    ->relationship('user', 'name')->searchable()->preload()
                    ->helperText('Если сотрудник сам вносит расходы и журнал'),
                Textarea::make('notes')->label('Примечание')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Grid::make(4)->schema([
                    TextEntry::make('position')->label('Должность')->placeholder('—'),
                    TextEntry::make('phone')->label('Телефон')->placeholder('—'),
                    TextEntry::make('day_rate')->label('Ставка')->money('RUB', locale: 'ru'),
                    TextEntry::make('card_percent')->label('% на карту')->suffix('%'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum('workLogs', 'amount')
                ->withSum('payouts', 'amount')
                ->withSum(['advances as issued_sum' => fn ($q) => $q->where('type', AdvanceType::Issue->value)], 'amount')
                ->withSum(['advances as returned_sum' => fn ($q) => $q->where('type', AdvanceType::Return->value)], 'amount')
                ->withSum(['expenses as spent_sum' => fn ($q) => $q->where('payment_source', PaymentSource::Advance->value)], 'amount'))
            ->columns([
                TextColumn::make('name')->label('ФИО')->searchable()->sortable()
                    ->description(fn (Employee $r) => $r->position),
                TextColumn::make('phone')->label('Телефон')->toggleable(),
                TextColumn::make('day_rate')->label('Ставка')->money('RUB', locale: 'ru')->alignEnd(),
                TextColumn::make('debt')->label('Долг по зарплате')->alignEnd()
                    ->state(fn (Employee $r) => (float) $r->work_logs_sum_amount - (float) $r->payouts_sum_amount)
                    ->formatStateUsing(fn ($state) => Money::fmt($state))
                    ->color(fn ($state) => $state > 0 ? 'warning' : null)
                    ->visible(fn () => Fields::isOffice()),
                TextColumn::make('advance')->label('Подотчёт на руках')->alignEnd()
                    ->state(fn (Employee $r) => (float) $r->issued_sum - (float) $r->returned_sum - (float) $r->spent_sum)
                    ->formatStateUsing(fn ($state) => Money::fmt($state))
                    ->color(fn ($state) => $state > 0 ? 'warning' : null)
                    ->visible(fn () => Fields::isOffice()),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Работает')->default(true)])
            ->defaultSort('name')
            ->recordUrl(fn (Employee $r) => Fields::isOffice() ? static::getUrl('view', ['record' => $r]) : null)
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WorkLogsRelationManager::class,
            RelationManagers\PayoutsRelationManager::class,
            RelationManagers\AdvancesRelationManager::class,
            RelationManagers\AdvanceExpensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
