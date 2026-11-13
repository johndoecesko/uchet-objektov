<?php

namespace App\Console\Commands;

use App\Support\Demo\Demo;
use App\Support\Demo\DemoReset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Публичное демо: создать (--create), пересоздать данные (без опций — так зовёт планировщик в 04:30 МСК),
 * удалить целиком (--destroy --force). При выключенном демо (DEMO_ENABLED) без опций ничего не делает;
 * --force — пересоздать и при выключенном (проверка на сервере до включения).
 */
class DemoResetCommand extends Command
{
    protected $signature = 'app:demo-reset
        {--create : создать демо-организацию и персонажей, затем заполнить}
        {--destroy : удалить демо целиком (вместе с --force)}
        {--force : без --destroy — пересоздать и при выключенном демо}';

    protected $description = 'Публичное демо: создание, ночное пересоздание данных, удаление';

    public function handle(DemoReset $reset): int
    {
        try {
            if ($this->option('destroy')) {
                if (! $this->option('force')) {
                    $this->error('Удаление демо — только вместе с --force');

                    return self::FAILURE;
                }
                $reset->destroy(true);
                $this->info('Демо удалено: данные, участники, персонажи, организация.');

                return self::SUCCESS;
            }

            if ($this->option('create')) {
                $org = $reset->ensureOrganization(create: true);
                $this->info("Демо-организация создана: id {$org->id}, /app/{$org->slug}");
            } elseif (! Demo::enabled() && ! $this->option('force')) {
                $this->line('Демо выключено (DEMO_ENABLED) — пересоздание пропущено.');

                return self::SUCCESS;
            }

            $counts = $reset->run();
            $this->info('Демо пересоздано: '.collect($counts)->filter()->map(fn ($n, $t) => "{$t} {$n}")->implode(', '));

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('app:demo-reset: '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
