<?php

namespace App\Support\Max;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bot API мессенджера MAX (https://dev.max.ru/docs-api).
 * Токен — только в заголовке Authorization; в query больше не принимается.
 */
class MaxApi
{
    public static function enabled(): bool
    {
        return filled(config('services.max.token'));
    }

    private function http(): PendingRequest
    {
        if (! self::enabled()) {
            throw new RuntimeException('MAX_BOT_TOKEN не задан');
        }

        return Http::baseUrl(rtrim((string) config('services.max.url'), '/'))
            ->withHeaders(['Authorization' => (string) config('services.max.token')])
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 500, throw: false);
    }

    /**
     * Сообщение пользователю. $buttons — строки кнопок: [[['text' => 'Да', 'payload' => 'yes']], …]
     */
    public function send(int $userId, string $text, array $buttons = []): void
    {
        $body = ['text' => mb_substr($text, 0, 4000)];
        if ($buttons) {
            $body['attachments'] = [[
                'type' => 'inline_keyboard',
                'payload' => ['buttons' => array_map(
                    fn (array $row) => array_map(fn (array $b) => isset($b['url'])
                        ? ['type' => 'link', 'text' => $b['text'], 'url' => $b['url']]
                        : ['type' => 'callback', 'text' => mb_substr($b['text'], 0, 64), 'payload' => $b['payload']], $row),
                    $buttons,
                )],
            ]];
        }

        $this->http()->post('/messages?user_id='.$userId, $body)->throw();
    }

    /** Ответ на нажатие кнопки — убирает «часики» у пользователя */
    public function answer(string $callbackId, ?string $notification = null): void
    {
        $this->http()->post('/answers?callback_id='.urlencode($callbackId), ['notification' => $notification]);
    }

    /** Скачать вложение (фото/файл) по ссылке из события */
    public function download(string $url, int $maxBytes): string
    {
        $response = Http::timeout(30)->get($url)->throw();
        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            throw new RuntimeException('Файл больше '.round($maxBytes / 1048576).' МБ');
        }

        return $body;
    }

    public function me(): array
    {
        return $this->http()->get('/me')->throw()->json();
    }

    public function subscriptions(): array
    {
        return $this->http()->get('/subscriptions')->throw()->json('subscriptions') ?? [];
    }

    /** @throws RequestException */
    public function subscribe(string $url, string $secret, array $types): array
    {
        return $this->http()->post('/subscriptions', ['url' => $url, 'update_types' => $types, 'secret' => $secret])->throw()->json();
    }

    public function unsubscribe(string $url): array
    {
        return $this->http()->delete('/subscriptions?url='.urlencode($url))->throw()->json();
    }
}
