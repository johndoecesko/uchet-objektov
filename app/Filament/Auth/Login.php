<?php

namespace App\Filament\Auth;

use App\Support\Registration;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use SensitiveParameter;

/**
 * Вход. Ссылка «Зарегистрироваться» — только когда регистрация открыта или есть приглашение.
 * Демо-учётки по паролю не входят (только кнопкой «Посмотреть демо»): ответ тот же, что на неверный пароль.
 */
class Login extends BaseLogin
{
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [...parent::getCredentialsFromFormData($data), 'is_demo' => false];
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }
        if (! Registration::allowed()) {
            return null;
        }

        $invitation = Registration::pendingInvitation();
        $before = $invitation ? 'Приглашение в «'.e($invitation->organization->name).'». Нет учётной записи?' : 'Нет учётной записи?';

        return new HtmlString($before.' '.$this->registerAction->toHtml());
    }
}
