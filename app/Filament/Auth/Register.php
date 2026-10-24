<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\Registration;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Регистрация по почте и паролю. Пока открытая регистрация выключена — только по приглашению:
 * почта из приглашения подставляется сама и считается подтверждённой (ссылка пришла на неё).
 */
class Register extends BaseRegister
{
    public function mount(): void
    {
        abort_unless(Registration::allowed(), 404);

        parent::mount();

        if ($email = Registration::pendingInvitation()?->email) {
            $this->form->fill(['email' => $email]);
        }
    }

    public function register(): ?RegistrationResponse
    {
        abort_unless(Registration::allowed(), 404);

        return parent::register();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $invitation = Registration::pendingInvitation();

        return $invitation
            ? 'Приглашение в «'.$invitation->organization->name.'». Уже есть учётная запись? '.$this->loginAction->toHtml()
            : parent::getSubheading();
    }

    protected function getEmailFormComponent(): Component
    {
        $field = parent::getEmailFormComponent();

        return Registration::pendingInvitation()?->email
            ? $field->readOnly()->helperText('Почта из приглашения')
            : $field;
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $invitation = Registration::pendingInvitation();
        if ($invitation?->email) {
            $data['email'] = $invitation->email; // поле только для чтения, но значение берём с сервера
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
        ]);

        if ($invitation?->email && $invitation->allows($user)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
