<?php

namespace App\Filament\Platform\Widgets;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\BackupOffsite;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Сводка платформы: организации, учётные записи, регистрации, копия в облако */
class PlatformStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $week = now()->subDays(7);
        $backupDays = BackupOffsite::enabled() ? BackupOffsite::daysSinceLast() : null;

        return [
            Stat::make('Организаций', (string) Organization::count())
                ->description('новых за 7 дней: '.Organization::where('created_at', '>=', $week)->count()
                    .' · заблокировано: '.Organization::where('status', OrganizationStatus::Blocked->value)->count()),
            Stat::make('Учётных записей', (string) User::count())
                ->description('новых за 7 дней: '.User::where('created_at', '>=', $week)->count()
                    .' · входили за 7 дней: '.User::where('last_login_at', '>=', $week)->count()),
            Stat::make('Копия в облако', ! BackupOffsite::enabled() ? 'не настроена' : ($backupDays === null ? 'ни разу' : ($backupDays === 0 ? 'сегодня' : $backupDays.' дн. назад')))
                ->color(BackupOffsite::enabled() && ($backupDays === null || $backupDays >= 2) ? 'danger' : null)
                ->description('Ночной бэкап базы и файлов — app:backup'),
        ];
    }
}
