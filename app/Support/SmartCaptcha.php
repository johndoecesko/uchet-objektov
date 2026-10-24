<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Яндекс SmartCaptcha (https://yandex.cloud/ru/docs/smartcaptcha/) на регистрации и сбросе пароля.
 * Без ключей (локально, в тестах) выключена. Токен одноразовый и живёт 5 минут.
 * Сервис не ответил (не 200, обрыв) — пропускаем, как советует документация: иначе из-за сбоя у Яндекса не зарегистрироваться.
 */
class SmartCaptcha
{
    public static function enabled(): bool
    {
        return filled(config('services.smartcaptcha.client_key')) && filled(config('services.smartcaptcha.server_key'));
    }

    public static function clientKey(): ?string
    {
        return config('services.smartcaptcha.client_key');
    }

    public static function passes(?string $token, ?string $ip): bool
    {
        if (! self::enabled()) {
            return true;
        }
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(config('services.smartcaptcha.url'), [
                'secret' => config('services.smartcaptcha.server_key'),
                'token' => $token,
                'ip' => $ip,
            ]);
        } catch (Throwable $e) {
            Log::warning('SmartCaptcha недоступна: '.$e->getMessage());

            return true;
        }

        if ($response->status() !== 200) {
            // 401/403 — неверный ключ сервера: капча фактически не работает, это надо увидеть в логе
            $response->status() === 401 || $response->status() === 403
                ? Log::error('SmartCaptcha: ключ сервера не принят ('.$response->status().'), проверка пропущена')
                : Log::warning('SmartCaptcha ответила '.$response->status());

            return true;
        }

        return $response->json('status') === 'ok';
    }

    /** Правило проверки для поля формы (объект, а не замыкание: замыкания Filament вызывает сам как фабрику правил) */
    public static function rule(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (! SmartCaptcha::passes(is_string($value) ? $value : null, request()->ip())) {
                    $fail('Подтвердите, что вы не робот.');
                }
            }
        };
    }
}
