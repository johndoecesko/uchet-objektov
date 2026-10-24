<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/** Письмо «подтвердите почту» после регистрации (ссылку собирает Filament, подписанная, 60 минут) */
class VerifyEmail extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Подтвердите почту — Учёт объектов')
            ->greeting('Здравствуйте!')
            ->line('Вы зарегистрировались в сервисе «Учёт объектов». Чтобы закончить регистрацию, подтвердите, что это ваша почта.')
            ->action('Подтвердить почту', $this->url)
            ->line('Ссылка действует 60 минут. Если она устарела, войдите в сервис — там можно отправить письмо ещё раз.')
            ->line('Если вы не регистрировались, просто не отвечайте на это письмо.')
            ->salutation('С уважением, «Учёт объектов»');
    }
}
