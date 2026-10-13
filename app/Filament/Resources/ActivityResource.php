<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use App\Filament\Support\Fields;
use App\Models\User;
use App\Support\History;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'history';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'запись истории';

    protected static ?string $pluralModelLabel = 'История изменений';

    protected static bool $hasTitleCaseModelLabel = false;

    /** Колонки истории — общие для раздела и для блока на странице объекта/сотрудника */
    public static function tableColumns(bool $withSubject = true): array
    {
        return array_values(array_filter([
            TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i')->sortable(),
            TextColumn::make('causer.name')->label('Кто')->placeholder('система'),
            $withSubject ? TextColumn::make('subject_type')->label('Что')
                ->state(fn (Activity $r) => History::subjectTitle($r))
                ->url(fn (Activity $r) => History::subjectUrl($r))
                ->color(fn (Activity $r) => History::subjectUrl($r) ? 'primary' : null)
                ->wrap() : null,
            TextColumn::make('event')->label('Событие')->badge()
                ->formatStateUsing(fn (?string $state) => History::eventLabel($state))
                ->color(fn (?string $state) => History::EVENT_COLORS[$state] ?? 'gray'),
            TextColumn::make('properties')->label('Изменения')
                ->state(fn (Activity $r) => History::changes($r) ?: null)
                ->listWithLineBreaks()
                ->limitList(6)
                ->expandableLimitedList()
                ->placeholder('—')
                ->wrap(),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->filters([
                Fields::periodFilter('created_at'),
                SelectFilter::make('subject_type')->label('Раздел')->options(History::MODELS),
                SelectFilter::make('event')->label('Событие')->options(History::EVENTS),
                SelectFilter::make('causer_id')->label('Кто')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }
}
