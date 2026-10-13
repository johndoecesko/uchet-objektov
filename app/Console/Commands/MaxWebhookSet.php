<?php

namespace App\Console\Commands;

use App\Support\Max\MaxApi;
use Illuminate\Console\Command;
use Throwable;

class MaxWebhookSet extends Command
{
    protected $signature = 'max:webhook-set {--delete : Снять подписку этого сайта}';

    protected $description = 'Бот MAX: подписать вебхук сайта (токен и секрет — из .env)';

    public const TYPES = ['message_created', 'message_callback', 'bot_started'];

    public function handle(MaxApi $api): int
    {
        if (! MaxApi::enabled()) {
            $this->error('MAX_BOT_TOKEN не задан в .env');

            return self::FAILURE;
        }

        $url = route('max.webhook');

        try {
            return $this->option('delete') ? $this->delete($api, $url) : $this->set($api, $url);
        } catch (Throwable $e) {
            // текст ошибки без заголовков запроса — токен не печатается
            $this->error('MAX API: '.mb_substr($e->getMessage(), 0, 300));

            return self::FAILURE;
        }
    }

    private function set(MaxApi $api, string $url): int
    {
        $secret = (string) config('services.max.webhook_secret');
        if (! preg_match('/^[a-zA-Z0-9_-]{5,256}$/', $secret)) {
            $this->error('MAX_WEBHOOK_SECRET не задан или содержит недопустимые символы (нужно 5–256 символов A-Z a-z 0-9 _ -)');

            return self::FAILURE;
        }
        $result = $api->subscribe($url, $secret, self::TYPES);
        $this->line(($result['success'] ?? false) ? "Подписан: {$url}" : 'MAX ответил: '.($result['message'] ?? 'ошибка'));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function delete(MaxApi $api, string $url): int
    {
        $result = $api->unsubscribe($url);
        $this->line(($result['success'] ?? false) ? "Отписан: {$url}" : 'MAX ответил: '.($result['message'] ?? 'ошибка'));

        return self::SUCCESS;
    }
}
