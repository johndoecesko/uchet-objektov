<?php

namespace App\Console\Commands;

use App\Support\Max\MaxApi;
use Illuminate\Console\Command;
use Throwable;

class MaxWebhookInfo extends Command
{
    protected $signature = 'max:webhook-info';

    protected $description = 'Бот MAX: имя бота и текущие подписки вебхука';

    public function handle(MaxApi $api): int
    {
        if (! MaxApi::enabled()) {
            $this->error('MAX_BOT_TOKEN не задан в .env');

            return self::FAILURE;
        }

        $url = route('max.webhook');

        try {
            return $this->show($api, $url);
        } catch (Throwable $e) {
            // текст ошибки без заголовков запроса — токен не печатается
            $this->error('MAX API: '.mb_substr($e->getMessage(), 0, 300));

            return self::FAILURE;
        }
    }

    private function show(MaxApi $api, string $url): int
    {
        $me = $api->me();
        $this->line('Бот: '.($me['name'] ?? $me['first_name'] ?? '?').(isset($me['username']) ? ' (@'.$me['username'].')' : '').', id '.($me['user_id'] ?? '?'));
        $this->line('Адрес вебхука сайта: '.$url);
        $subs = $api->subscriptions();
        if (! $subs) {
            $this->warn('Подписок нет — выполните: php artisan max:webhook-set');
        }
        foreach ($subs as $s) {
            $this->line('— '.($s['url'] ?? '?').' ['.implode(', ', $s['update_types'] ?? ['все']).']'.(($s['url'] ?? '') === $url ? ' ← этот сайт' : ''));
        }

        return self::SUCCESS;
    }
}
