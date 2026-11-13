<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\MemberResource;
use App\Filament\Resources\MemberResource\Widgets\PendingInvitations;
use App\Models\Invitation;
use App\Notifications\OrganizationInvitation;
use App\Support\CurrentOrganization;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Notification as Notifier;
use Illuminate\Support\Js;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        $role = Select::make('role')->label('Роль')->options(UserRole::class)->default(UserRole::Foreman)->required()
            ->helperText(MemberResource::roleHint());

        return [
            Action::make('inviteLink')
                ->label('Ссылка-приглашение')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->authorize(fn () => auth()->user()?->can('create', Invitation::class) ?? false)
                ->modalHeading('Ссылка-приглашение')
                ->modalDescription('Одноразовая ссылка на 7 дней — отправьте её в мессенджер. Кто первым откроет и войдёт (или зарегистрируется), станет участником.')
                ->modalSubmitActionLabel('Создать ссылку')
                ->schema([$role])
                ->action(function (array $data) {
                    abort_unless(auth()->user()->can('create', Invitation::class), 403);
                    $invite = Invitation::create(['role' => $data['role']]);
                    self::linkNotification($invite)->send();
                    $this->dispatch('invitations-changed');
                }),
            Action::make('inviteEmail')
                ->label('Пригласить по почте')
                ->icon('heroicon-o-envelope')
                ->authorize(fn () => auth()->user()?->can('create', Invitation::class) ?? false)
                ->modalHeading('Пригласить по почте')
                ->modalDescription('Придёт письмо со ссылкой на 7 дней. Есть учётная запись — войдёт и попадёт в организацию; нет — зарегистрируется с этой почтой.')
                ->modalSubmitActionLabel('Отправить приглашение')
                ->schema([
                    TextInput::make('email')->label('Email')->email()->required()->maxLength(255),
                    $role,
                ])
                ->action(function (array $data) {
                    abort_unless(auth()->user()->can('create', Invitation::class), 403);
                    $invite = Invitation::create(['email' => $data['email'], 'role' => $data['role']]);
                    Notifier::route('mail', $invite->email)->notify(new OrganizationInvitation($invite, app(CurrentOrganization::class)->get(), auth()->user()));
                    Notification::make()->title('Приглашение отправлено на '.$invite->email)->success()->send();
                    $this->dispatch('invitations-changed');
                }),
        ];
    }

    /** Уведомление со ссылкой и кнопкой «Скопировать» */
    public static function linkNotification(Invitation $invite): Notification
    {
        $url = $invite->url();

        return Notification::make()
            ->title('Ссылка-приглашение ('.$invite->role->getLabel().')')
            ->body($url)
            ->persistent()
            ->success()
            ->actions([
                Action::make('copy')->label('Скопировать')->button()
                    // Js::from — строка в одинарных кавычках, не ломает HTML-атрибут
                    ->alpineClickHandler('window.navigator.clipboard.writeText('.Js::from($url).'); $tooltip(\'Скопировано\', { timeout: 1500 })'),
            ]);
    }

    protected function getFooterWidgets(): array
    {
        return [PendingInvitations::class];
    }
}
