<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Настройки приложения «ключ → значение». Читаются через кэш, сбрасываются при сохранении */
class Setting extends Model
{
    use LogsChanges;

    /** Значения по умолчанию — пока в базе ничего не сохранено */
    public const DEFAULTS = [
        'attention.advance_days' => 14,     // подотчёт без отчёта дольше N дней — в «Требует внимания»
        'attention.low_margin' => 10.0,     // прогноз рентабельности ниже N % — предупреждение
    ];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings'));
        static::deleted(fn () => Cache::forget('settings'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
