<?php

namespace App\Filament\Auth;

use App\Support\Registration;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/** Вход. Ссылка «Зарегистрироваться» — только когда регистрация открыта или есть приглашение */
class Login extends BaseLogin
{
    public function getSubheading(): string | Htmlable | null
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
