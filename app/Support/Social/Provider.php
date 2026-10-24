<?php

namespace App\Support\Social;

use Illuminate\Http\Request;

/**
 * Вход через внешний аккаунт: OAuth 2.0 «код подтверждения» + PKCE (S256). Без пакетов — у обоих провайдеров три запроса.
 * Без ключей в .env провайдер выключен: кнопки не показываются, адреса отвечают 404.
 */
abstract class Provider
{
    /** @var array<string, class-string<Provider>> */
    public const PROVIDERS = ['yandex' => YandexId::class, 'vkid' => VkId::class];

    abstract public function name(): string;

    abstract public function label(): string;

    abstract public function enabled(): bool;

    /** Адрес страницы входа провайдера */
    abstract public function authorizeUrl(string $state, string $codeChallenge): string;

    /** Обменять код из ответа провайдера на пользователя */
    abstract public function user(Request $request, string $codeVerifier, string $state): SocialUser;

    public static function find(string $name): ?self
    {
        $class = self::PROVIDERS[$name] ?? null;

        return $class ? app($class) : null;
    }

    /** @return array<string, Provider> включённые */
    public static function enabledAll(): array
    {
        return collect(self::PROVIDERS)
            ->map(fn (string $class) => app($class))
            ->filter(fn (Provider $p) => $p->enabled())
            ->all();
    }

    public function redirectUri(): string
    {
        return route('social.callback', $this->name());
    }

    public static function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    protected function config(string $key): ?string
    {
        $value = config('services.'.$this->name().'.'.$key);

        return filled($value) ? (string) $value : null;
    }
}
