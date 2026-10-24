<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsChanges;
use App\Support\CurrentOrganization;
use App\Support\OrganizationMismatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Настройки организации «ключ → значение». Читаются через кэш (свой у каждой организации), сбрасываются при сохранении */
class Setting extends Model
{
    use BelongsToOrganization, LogsChanges;

    /** Значения по умолчанию — пока в базе ничего не сохранено */
    public const DEFAULTS = [
        'attention.advance_days' => 14,     // подотчёт без отчёта дольше N дней — в «Требует внимания»
        'attention.low_margin' => 10.0,     // прогноз рентабельности ниже N % — предупреждение
    ];

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $s) => Cache::forget(self::cacheKey($s->organization_id)));
        static::deleted(fn (Setting $s) => Cache::forget(self::cacheKey($s->organization_id)));
    }

    private static function cacheKey(?int $organizationId): string
    {
        return 'settings:'.($organizationId ?? 0);
    }

    /** Значение для текущей организации (вне организации — значение по умолчанию) */
    public static function get(string $key, mixed $default = null): mixed
    {
        $orgId = app(CurrentOrganization::class)->id();
        $all = $orgId
            ? Cache::rememberForever(self::cacheKey($orgId), fn () => static::query()->pluck('value', 'key')->all())
            : [];

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        $orgId = app(CurrentOrganization::class)->id()
            ?? throw new OrganizationMismatch('Настройка сохраняется без организации');
        static::updateOrCreate(['organization_id' => $orgId, 'key' => $key], ['value' => $value]);
    }
}
