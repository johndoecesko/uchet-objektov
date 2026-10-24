<?php

namespace App\Support\Social;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Яндекс ID (https://yandex.ru/dev/id/doc/ru/). Права приложения: почта, логин/имя.
 * default_email — контактный адрес из Яндекс ID. Документация не говорит прямо, подтверждён ли он; по задаче
 * почта от провайдера считается подтверждённой (это даёт привязку к существующей учётке с той же почтой).
 */
class YandexId extends Provider
{
    public function name(): string
    {
        return 'yandex';
    }

    public function label(): string
    {
        return 'Яндекс ID';
    }

    public function enabled(): bool
    {
        return $this->config('client_id') !== null && $this->config('client_secret') !== null;
    }

    public function authorizeUrl(string $state, string $codeChallenge): string
    {
        return 'https://oauth.yandex.ru/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config('client_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function user(Request $request, string $codeVerifier, string $state): SocialUser
    {
        $code = (string) $request->query('code');
        if ($code === '') {
            throw new SocialAuthFailed('Яндекс: нет кода ('.$request->query('error', '—').')');
        }

        $token = Http::asForm()->timeout(10)->post('https://oauth.yandex.ru/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'code_verifier' => $codeVerifier,
        ]);
        if (! $token->successful() || ! $token->json('access_token')) {
            throw new SocialAuthFailed('Яндекс: токен не выдан ('.$token->status().' '.$token->json('error').')');
        }

        $info = Http::withHeaders(['Authorization' => 'OAuth '.$token->json('access_token')])
            ->timeout(10)->get('https://login.yandex.ru/info', ['format' => 'json']);
        if (! $info->successful() || blank($info->json('id'))) {
            throw new SocialAuthFailed('Яндекс: нет данных пользователя ('.$info->status().')');
        }

        $email = $info->json('default_email');
        $name = $info->json('real_name') ?: $info->json('display_name') ?: $info->json('login');

        return new SocialUser('yandex', (string) $info->json('id'), filled($email) ? mb_strtolower($email) : null, filled($email), $name ?: null);
    }
}
