<?php

namespace App\Filament\Resources\MemberResource\Widgets;

use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Models\Invitation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

/** Приглашения, которые ещё не приняты: ссылку можно показать снова или отозвать */
class PendingInvitations extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    /** Новое приглашение со страницы «Участники» — перерисовать список */
    #[On('invitations-changed')]
    public function refreshInvitations(): void {}

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Приглашения, которые ещё не приняты')
            ->query(fn () => Invitation::query()->pending()->with('creator'))
            ->columns([
                TextColumn::make('email')->label('Кому')->placeholder('ссылка для мессенджера'),
                TextColumn::make('role')->label('Роль')->badge(),
                TextColumn::make('creator.name')->label('Пригласил')->placeholder('—'),
                TextColumn::make('expires_at')->label('Действует до')->dateTime('d.m.Y H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('link')->label('Ссылка')->icon('heroicon-o-link')->color('gray')
                    ->action(fn (Invitation $record) => ListMembers::linkNotification($record)->send()),
                DeleteAction::make()->label('Отозвать')->modalHeading('Отозвать приглашение?')
                    ->successNotificationTitle('Приглашение отозвано'),
            ])
            ->emptyStateHeading('Нет неотвеченных приглашений')
            ->paginated([10, 25]);
    }
}
