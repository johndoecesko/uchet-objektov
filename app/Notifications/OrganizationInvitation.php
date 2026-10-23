<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Письмо-приглашение в организацию (ссылка подписанная, 7 дней) */
class OrganizationInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invitation $invitation, public Organization $organization, public User $inviter) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Приглашение в «{$this->organization->name}» — Учёт объектов")
            ->greeting('Здравствуйте!')
            ->line("{$this->inviter->name} приглашает вас в организацию «{$this->organization->name}» в сервисе «Учёт объектов». Роль: {$this->invitation->role->getLabel()}.")
            ->line('В сервисе ведут учёт по монтажным объектам: расходы с фото чеков, журнал работ, выплаты и подотчёт.')
            ->action('Принять приглашение', $this->invitation->url())
            ->line('Ссылка действует до '.$this->invitation->expires_at->format('d.m.Y H:i').' и сработает один раз.')
            ->line('Если вы не ждали этого письма, просто не отвечайте на него.')
            ->salutation('С уважением, «Учёт объектов»');
    }
}
