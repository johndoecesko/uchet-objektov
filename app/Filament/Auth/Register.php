<?php

namespace App\Filament\Auth;

use App\Filament\Forms\SmartCaptchaField;
use App\Models\User;
use App\Support\AuthThrottle;
use App\Support\PlatformCounter;
use App\Support\Registration;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use SensitiveParameter;

/**
 * Регистрация по почте и паролю: открытая (REGISTRATION_ENABLED) или по приглашению.
 * Почта из почтового приглашения подставляется сама и считается подтверждённой (ссылка пришла на неё).
 * Обязательное согласие с документами (когда, редакция, IP — в users.consent_*), капча, суточный лимит с IP.
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

        if (AuthThrottle::exceeded('register')) {
            return null;
        }

        try {
            return parent::register();
        } finally {
            // токен капчи одноразовый: после любой попытки — новый
            $this->dispatch('smart-captcha-reset');
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        $invitation = Registration::pendingInvitation();

        return $invitation
            ? 'Приглашение в «'.$invitation->organization->name.'». Уже есть учётная запись? '.$this->loginAction->toHtml()
            : parent::getSubheading();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            ...$this->getConsentFormComponents(),
            SmartCaptchaField::make(),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        $field = parent::getEmailFormComponent();

        return Registration::pendingInvitation()?->email
            ? $field->readOnly()->helperText('Почта из приглашения')
            : $field;
    }

    /** Две отметки: согласие на обработку ПДн — отдельный документ без прочих условий (ч. 1 ст. 9 152-ФЗ в ред. 156-ФЗ) */
    protected function getConsentFormComponents(): array
    {
        return [
            Checkbox::make('terms')
                ->label(new HtmlString('Принимаю <a href="'.e(route('terms')).'" target="_blank" class="underline">Пользовательское соглашение</a>'))
                ->accepted()
                ->validationMessages(['accepted' => 'Без согласия с Пользовательским соглашением зарегистрироваться нельзя.'])
                ->dehydrated(false),
            Checkbox::make('consent')
                ->label(new HtmlString('Даю <a href="'.e(route('consent')).'" target="_blank" class="underline">согласие на обработку персональных данных</a>'))
                ->accepted()
                ->validationMessages(['accepted' => 'Без согласия на обработку персональных данных зарегистрироваться нельзя.'])
                ->dehydrated(false),
        ];
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

        // отметки проверила форма (accepted) — без них сюда не дойти
        $user->forceFill([
            'consent_at' => now(),
            'consent_version' => config('legal.version'),
            'consent_ip' => request()->ip(),
        ]);
        if ($invitation?->email && $invitation->allows($user)) {
            $user->forceFill(['email_verified_at' => now()]);
        }
        // Пришёл из демо (кнопка «Зарегистрироваться» на плашке) — для сводки в /platform
        $source = session()->pull('signup_source');
        if ($source === 'demo') {
            $user->forceFill(['signup_source' => 'demo']);
        }
        $user->save();
        if ($source === 'demo') {
            PlatformCounter::hit('demo.registered');
        }

        return $user;
    }
}
