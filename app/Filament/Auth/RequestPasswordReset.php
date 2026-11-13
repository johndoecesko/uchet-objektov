<?php

namespace App\Filament\Auth;

use App\Filament\Forms\SmartCaptchaField;
use App\Support\AuthThrottle;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;

/**
 * «Забыли пароль?»: на неизвестную почту отвечаем так же, как на известную — список учёток не перебрать.
 * Капча и суточный лимит с IP. Демо-учёткам письмо не уходит (ищем только среди настоящих), ответ — как обычно.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [...parent::getCredentialsFromFormData($data), 'is_demo' => false];
    }

    public function request(): void
    {
        if (AuthThrottle::exceeded('password-reset')) {
            return;
        }

        try {
            parent::request();
        } finally {
            $this->dispatch('smart-captcha-reset');
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            SmartCaptchaField::make(),
        ]);
    }

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
