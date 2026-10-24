<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/** «Забыли пароль?»: на неизвестную почту отвечаем так же, как на известную — список учёток не перебрать */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected function getFailureNotification(string $status): ?Notification
    {
        // «Письмо уже отправлено» тоже выдало бы, что почта есть в базе
        if (in_array($status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            $this->form->fill();

            return $this->getSentNotification(Password::RESET_LINK_SENT);
        }

        return parent::getFailureNotification($status);
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return Notification::make()
            ->title(__($status))
            ->body('Письмо приходит в течение пары минут. Проверьте и папку «Спам».')
            ->success();
    }
}
