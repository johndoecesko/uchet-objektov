<?php

namespace App\Filament\Platform\Resources;

use App\Enums\OrganizationStatus;
use App\Filament\Platform\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Организации платформы: служебная сводка (без сумм и названий объектов) и блокировка */
class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'организацию';

    protected static ?string $pluralModelLabel = 'Организации';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('№')->sortable(),
                TextColumn::make('name')->label('Организация')->searchable(['name', 'inn', 'slug'])->wrap()
                    ->description(fn (Organization $o) => trim(($o->inn ? 'ИНН '.$o->inn.' · ' : '').'/app/'.$o->slug)),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('creator.email')->label('Создал')->placeholder('—')->toggleable(),
                TextColumn::make('memberships_count')->label('Участников')->alignEnd()->sortable(),
                TextColumn::make('projects_count')->label('Объектов')->alignEnd()->sortable(),
                TextColumn::make('expenses_count')->label('Расходов')->alignEnd()->sortable(),
                TextColumn::make('created_at')->label('Регистрация')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('last_login_at')->label('Последний вход')->dateTime('d.m.Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label('Статус')->options(OrganizationStatus::class)])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('block')->label('Заблокировать')->icon('heroicon-o-lock-closed')->color('danger')
                    ->visible(fn (Organization $o) => ! $o->isBlocked())
                    ->requiresConfirmation()
                    ->modalDescription('Участники не смогут открыть организацию, бот перестанет принимать чеки в неё. Данные сохраняются.')
                    ->action(function (Organization $o) {
                        $o->update(['status' => OrganizationStatus::Blocked]);
                        Notification::make()->title("«{$o->name}» заблокирована")->success()->send();
                    }),
                Action::make('unblock')->label('Разблокировать')->icon('heroicon-o-lock-open')->color('success')
                    ->visible(fn (Organization $o) => $o->isBlocked())
                    ->requiresConfirmation()
                    ->action(function (Organization $o) {
                        $o->update(['status' => OrganizationStatus::Active]);
                        Notification::make()->title("«{$o->name}» разблокирована")->success()->send();
                    }),
            ]);
    }

    /** Счётчики — служебная сводка; суммы и названия объектов сюда не попадают */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['memberships', 'projects', 'expenses'])
            ->addSelect(['last_login_at' => User::query()->selectRaw('max(last_login_at)')
                ->join('organization_user', 'organization_user.user_id', '=', 'users.id')
                ->whereColumn('organization_user.organization_id', 'organizations.id')])
            ->with('creator');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOrganizations::route('/')];
    }
}
