<?php

namespace App\Support\Social;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * VK ID, сервер авторизации 2.1 (https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/api-description).
 * Не старый oauth.vk.com. В ответе на redirect приходит device_id — без него код не обменять.
 * Почта приходит, только если в приложении включён доступ «почта» и пользователь его разрешил. Подтверждена ли она,
 * документация не говорит; по задаче почта от провайдера считается подтверждённой.
 * VKID_SERVICE_TOKEN — сервисный ключ, обязателен для «конфиденциального» приложения; для публичного (PKCE) не нужен.
 */
class VkId extends Provider
{
    private const BASE = 'https://id.vk.ru';

    public function name(): string
    {
        return 'vkid';
    }

    public function label(): string
    {
        return 'VK ID';
    }

    public function enabled(): bool
    {
        return $this->config('client_id') !== null;
    }

    public function authorizeUrl(string $state, string $codeChallenge): string
    {
        return self::BASE.'/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config('client_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'scope' => 'email',
        ]);
    }

    public function user(Request $request, string $codeVerifier, string $state): SocialUser
    {
        $code = (string) $request->query('code');
        $deviceId = (string) $request->query('device_id');
        if ($code === '' || $deviceId === '') {
            throw new SocialAuthFailed('VK ID: нет кода или device_id ('.$request->query('error', '—').')');
        }

        $params = [
            'grant_type' => 'authorization_code',
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
            'client_id' => $this->config('client_id'),
            'device_id' => $deviceId,
            'state' => $state,
        ];
        if ($serviceToken = $this->config('service_token')) {
            $params['service_token'] = $serviceToken;
        }

        $token = Http::asForm()->timeout(10)->post(self::BASE.'/oauth2/auth', $params);
        if (! $token->successful() || ! $token->json('access_token')) {
            throw new SocialAuthFailed('VK ID: токен не выдан ('.$token->status().' '.$token->json('error').')');
        }

        $info = Http::asForm()->timeout(10)->post(self::BASE.'/oauth2/user_info', [
            'client_id' => $this->config('client_id'),
            'access_token' => $token->json('access_token'),
        ]);
        $user = $info->json('user');
        if (! $info->successful() || ! is_array($user) || blank($user['user_id'] ?? null)) {
            throw new SocialAuthFailed('VK ID: нет данных пользователя ('.$info->status().')');
        }

        $email = $user['email'] ?? null;
        $name = trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? ''));

        return new SocialUser('vkid', (string) $user['user_id'], filled($email) ? mb_strtolower($email) : null, filled($email), $name !== '' ? $name : null);
    }
}
