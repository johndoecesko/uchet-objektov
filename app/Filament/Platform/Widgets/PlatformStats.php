<?php

namespace App\Filament\Platform\Widgets;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\BackupOffsite;
use App\Support\Demo\Demo;
use App\Support\PlatformCounter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Сводка платформы: организации, учётные записи, регистрации, демо, копия в облако. Демо-организация и её персонажи не считаются */
class PlatformStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $week = now()->subDays(7);
        $backupDays = BackupOffsite::enabled() ? BackupOffsite::daysSinceLast() : null;

        $orgs = fn () => Organization::query()->real();
        $users = fn () => User::query()->where('is_demo', false);

        return [
            Stat::make('Организаций', (string) $orgs()->count())
                ->description('новых за 7 дней: '.$orgs()->where('created_at', '>=', $week)->count()
                    .' · заблокировано: '.$orgs()->where('status', OrganizationStatus::Blocked->value)->count()),
            Stat::make('Учётных записей', (string) $users()->count())
                ->description('новых за 7 дней: '.$users()->where('created_at', '>=', $week)->count()
                    .' · входили за 7 дней: '.$users()->where('last_login_at', '>=', $week)->count()),
            Stat::make('Демо за 7 дней', PlatformCounter::sum('demo.enter', 7).' входов')
                ->description('перешли к регистрации: '.PlatformCounter::sum('demo.signup_click', 7)
                    .' · зарегистрировались: '.PlatformCounter::sum('demo.registered', 7)
                    .(Demo::enabled() ? '' : ' · демо выключено')),
            Stat::make('Копия в облако', ! BackupOffsite::enabled() ? 'не настроена' : ($backupDays === null ? 'ни разу' : ($backupDays === 0 ? 'сегодня' : $backupDays.' дн. назад')))
                ->color(BackupOffsite::enabled() && ($backupDays === null || $backupDays >= 2) ? 'danger' : null)
                ->description('Ночной бэкап базы и файлов — app:backup'),
        ];
    }
}
