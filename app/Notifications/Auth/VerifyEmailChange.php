<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\VerifyEmailChange as BaseVerifyEmailChange;
use Illuminate\Notifications\Messages\MailMessage;

/** Письмо на новую почту при её смене в профиле: почта меняется только после перехода по ссылке */
class VerifyEmailChange extends BaseVerifyEmailChange
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Подтвердите новую почту — Учёт объектов')
            ->greeting('Здравствуйте!')
            ->line('В профиле сервиса «Учёт объектов» указали этот адрес как новую почту для входа.')
            ->action('Подтвердить новую почту', $this->url)
            ->line('Пока вы не перейдёте по ссылке, вход остаётся по старой почте. Ссылка действует 60 минут.')
            ->line('Если вы ничего не меняли, просто не отвечайте на это письмо.')
            ->salutation('С уважением, «Учёт объектов»');
    }
}
