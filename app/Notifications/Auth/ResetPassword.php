<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** Письмо со ссылкой на сброс пароля (срок — config auth.passwords.users.expire) */
class ResetPassword extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Сброс пароля — Учёт объектов')
            ->greeting('Здравствуйте!')
            ->line('Кто-то (надеемся, вы) запросил сброс пароля для входа в сервис «Учёт объектов».')
            ->action('Задать новый пароль', $this->url)
            ->line("Ссылка действует {$minutes} минут и сработает один раз.")
            ->line('Если вы не запрашивали сброс, ничего делать не нужно — пароль останется прежним.')
            ->salutation('С уважением, «Учёт объектов»');
    }
}
