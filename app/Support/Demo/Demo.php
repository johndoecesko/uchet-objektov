<?php

namespace App\Support\Demo;

use App\Enums\UserRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Публичное демо: одна общая организация (organizations.is_demo) и её участники-персонажи (users.is_demo).
 * Гость входит кнопкой «Посмотреть демо» в персонажа-администратора и может только смотреть.
 *
 * Запрет записи — в три слоя:
 *  1. Gate::before (AppServiceProvider): демо-пользователю разрешены только viewAny и view;
 *  2. модели (BelongsToOrganization, Organization, User, Membership) зовут assertWritable() — любая запись
 *     в демо-организацию, в демо-учётку и любая запись от имени демо-пользователя бросает DemoReadOnly;
 *  3. DemoGuard закрывает маршруты: загрузка файлов, приглашения, соцсети, профиль, платформа.
 * Писать в демо может только пересоздание (DemoReset) — внутри allowWrites().
 */
class Demo
{
    /** Глубина вложенных allowWrites() — пишет только пересоздание демо */
    private static int $writesAllowed = 0;

    public static function enabled(): bool
    {
        return (bool) config('demo.enabled');
    }

    public static function slug(): string
    {
        return (string) config('demo.slug', 'demo');
    }

    /** Демо-организация, если создана */
    public static function organization(): ?Organization
    {
        return Organization::query()->where('is_demo', true)->orderBy('id')->first();
    }

    /**
     * Ровно одна демо-организация с адресом demo, все её участники — персонажи демо.
     * Любое нарушение — исключение (вход в демо отвечает 503, пересоздание не идёт).
     */
    public static function organizationOrFail(): Organization
    {
        $org = Organization::query()->where('is_demo', true)->sole();

        if ($org->slug !== self::slug()) {
            throw new RuntimeException("Демо-организация {$org->id} с адресом «{$org->slug}», ожидался «".self::slug().'»');
        }

        $strangers = Membership::acrossOrganizations()
            ->where('organization_id', $org->id)
            ->whereIn('user_id', User::query()->where('is_demo', false)->select('id'))
            ->count();
        if ($strangers > 0) {
            throw new RuntimeException("В демо-организации {$org->id} есть настоящие участники ({$strangers})");
        }

        return $org;
    }

    public static function isDemoUser(?User $user): bool
    {
        return $user !== null && (bool) $user->is_demo;
    }

    /** Вошедший сейчас — демо-пользователь */
    public static function active(): bool
    {
        $user = Auth::user();

        return $user instanceof User && self::isDemoUser($user);
    }

    public static function isDemoOrganizationId(mixed $id): bool
    {
        if (! is_numeric($id) || (int) $id <= 0) {
            return false;
        }

        return Organization::query()->whereKey((int) $id)->where('is_demo', true)->exists();
    }

    /** Выполнить код с разрешённой записью в демо — только для пересоздания (DemoReset) */
    public static function allowWrites(Closure $callback): mixed
    {
        self::$writesAllowed++;

        try {
            return $callback();
        } finally {
            self::$writesAllowed--;
        }
    }

    public static function writesAllowed(): bool
    {
        return self::$writesAllowed > 0;
    }

    /**
     * Бросает DemoReadOnly, если запись не разрешена allowWrites() и:
     * вошёл демо-пользователь (что бы он ни писал), или запись — в демо-организацию,
     * или это сама демо-организация, или демо-учётка.
     */
    public static function assertWritable(Model $model): void
    {
        if (self::writesAllowed()) {
            return;
        }

        if (self::active()) {
            throw new DemoReadOnly;
        }

        if ($model instanceof Organization || $model instanceof User) {
            if ($model->getAttribute('is_demo') || $model->getOriginal('is_demo')) {
                throw new DemoReadOnly;
            }

            return;
        }

        foreach (array_unique([$model->getAttribute('organization_id'), $model->getOriginal('organization_id')]) as $id) {
            if (self::isDemoOrganizationId($id)) {
                throw new DemoReadOnly;
            }
        }
    }

    /**
     * Пользователь для входа в демо: персонаж-администратор демо-организации.
     * Проверяется всё, что делает вход безопасным; нарушен любой инвариант — исключение, вход не состоится.
     */
    public static function entryUser(): User
    {
        $org = self::organizationOrFail();

        $user = User::query()
            ->where('is_demo', true)
            ->whereIn('id', Membership::acrossOrganizations()
                ->where('organization_id', $org->id)
                ->where('role', UserRole::Admin->value)
                ->select('user_id'))
            ->sole();

        $memberships = Membership::acrossOrganizations()->where('user_id', $user->id)->get();
        $membership = $memberships->first();

        $problems = array_filter([
            $user->is_platform_admin ? 'администратор платформы' : null,
            ! $user->is_active ? 'учётка отключена' : null,
            $memberships->count() !== 1 ? 'членств: '.$memberships->count() : null,
            $membership && (int) $membership->organization_id !== (int) $org->id ? 'членство не в демо' : null,
            $membership && ($membership->role !== UserRole::Admin || ! $membership->is_active) ? 'роль не «администратор» или доступ выключен' : null,
            $user->email_verified_at === null ? 'почта не подтверждена' : null,
        ]);
        if ($problems !== []) {
            throw new RuntimeException("Демо-пользователь {$user->id}: ".implode(', ', $problems));
        }

        return $user;
    }

    /** Удалить сессии демо-пользователей без активности дольше session_idle_hours (только при сессиях в базе) */
    public static function pruneSessions(?int $idleHours = null): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }
        $table = config('session.table', 'sessions');
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $hours = $idleHours ?? (int) config('demo.session_idle_hours', 3);

        return DB::table($table)
            ->whereIn('user_id', User::query()->where('is_demo', true)->select('id'))
            ->when($hours > 0, fn ($q) => $q->where('last_activity', '<', now()->subHours($hours)->getTimestamp()))
            ->delete();
    }

    /** Все сессии демо-пользователей (демо выключили) */
    public static function dropAllSessions(): int
    {
        return self::pruneSessions(0);
    }

    /** Выгрузка Excel в демо: true — можно, false — лимит на час исчерпан */
    public static function throttleExport(): bool
    {
        $key = 'demo-export:'.session()->getId().':'.request()->ip();
        $max = (int) config('demo.export_per_hour', 10);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return false;
        }
        RateLimiter::hit($key, 3600);

        return true;
    }

    /** Для действий-выгрузок: демо-пользователю — с лимитом (429 сверх лимита), остальным — без */
    public static function guardExport(): void
    {
        abort_if(self::active() && ! self::throttleExport(), 429, 'В демо — не больше '.config('demo.export_per_hour', 10).' выгрузок в час');
    }
}
