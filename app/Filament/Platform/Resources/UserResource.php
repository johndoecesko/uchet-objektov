<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Учётные записи платформы: где состоят, когда входили; заблокировать / разблокировать вход */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'учётную запись';

    protected static ?string $pluralModelLabel = 'Учётные записи';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('№')->sortable(),
                TextColumn::make('name')->label('Имя')->searchable(),
                TextColumn::make('demo')->label('Демо')->badge()->color('warning')->placeholder('')
                    ->state(fn (User $u) => $u->is_demo ? 'Демо' : null)
                    ->tooltip(fn (User $u) => $u->is_demo ? 'Персонаж демо: входа по паролю нет' : null),
                TextColumn::make('email')->label('Email')->searchable()
                    ->description(fn (User $u) => $u->email_verified_at ? null : 'почта не подтверждена'),
                TextColumn::make('organizations.name')->label('Организации')->listWithLineBreaks()->limitList(3)->placeholder('—'),
                IconColumn::make('is_active')->label('Вход')->boolean(),
                IconColumn::make('is_platform_admin')->label('Админ платформы')->boolean()->toggleable(),
                TextColumn::make('created_at')->label('Регистрация')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('last_login_at')->label('Последний вход')->dateTime('d.m.Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Вход разрешён')])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('block')->label('Заблокировать')->icon('heroicon-o-lock-closed')->color('danger')
                    ->visible(fn (User $u) => $u->is_active && $u->id !== auth()->id() && ! $u->is_demo)
                    ->requiresConfirmation()
                    ->modalDescription('Человек не сможет войти ни в одну организацию, бот MAX перестанет принимать от него чеки.')
                    ->action(function (User $u) {
                        abort_if($u->is_demo, 403, 'Демо отключается через DEMO_ENABLED');
                        $u->update(['is_active' => false]);
                        Notification::make()->title("{$u->email} заблокирован")->success()->send();
                    }),
                Action::make('unblock')->label('Разблокировать')->icon('heroicon-o-lock-open')->color('success')
                    ->visible(fn (User $u) => ! $u->is_active && ! $u->is_demo)
                    ->requiresConfirmation()
                    ->action(function (User $u) {
                        $u->update(['is_active' => true]);
                        Notification::make()->title("{$u->email} разблокирован")->success()->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('organizations:id,name');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUsers::route('/')];
    }
}
