<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Суточный лимит попыток с одного IP для регистрации и сброса пароля (поминутный — у самих страниц Filament).
 * Считаются все попытки, и неудачные тоже.
 */
class AuthThrottle
{
    public const PER_DAY = 20;

    /** true — лимит исчерпан (пользователю уже показано сообщение) */
    public static function exceeded(string $action): bool
    {
        $key = 'auth-day:'.$action.':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, self::PER_DAY)) {
            Notification::make()
                ->title('Слишком много попыток')
                ->body('С вашего адреса сегодня было слишком много попыток. Попробуйте завтра или напишите нам: '.config('app.support_email'))
                ->danger()
                ->send();

            return true;
        }
        RateLimiter::hit($key, 24 * 60 * 60);

        return false;
    }
}
