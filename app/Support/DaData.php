<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Подсказки DaData (https://dadata.ru/api/suggest/).
 * Запросы идут с сервера, ключ в браузер не попадает.
 */
class DaData
{
    public static function enabled(): bool
    {
        return filled(config('services.dadata.token'));
    }

    /** @return array<int, array<string, mixed>> */
    protected static function suggest(string $type, string $query, int $count = 10): array
    {
        $query = trim($query);
        if (! static::enabled() || mb_strlen($query) < 3) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token '.config('services.dadata.token'),
                'Accept' => 'application/json',
            ])->timeout(5)->post(rtrim(config('services.dadata.url'), '/').'/suggest/'.$type, [
                'query' => $query,
                'count' => $count,
            ]);

            if (! $response->successful()) {
                Log::warning('DaData '.$type.' HTTP '.$response->status());

                return [];
            }

            return $response->json('suggestions') ?? [];
        } catch (Throwable $e) {
            Log::warning('DaData '.$type.': '.$e->getMessage());

            return [];
        }
    }

    /**
     * Организации по ИНН, ОГРН или названию.
     *
     * @return array<string, string> ключ → подпись для выпадающего списка
     */
    public static function parties(string $query): array
    {
        $out = [];
        foreach (static::suggest('party', $query) as $s) {
            $d = $s['data'] ?? [];
            $party = [
                'name' => $s['value'] ?? ($d['name']['short_with_opf'] ?? ''),
                'inn' => $d['inn'] ?? null,
                'kpp' => $d['kpp'] ?? null,
                'ogrn' => $d['ogrn'] ?? null,
                'address' => $d['address']['unrestricted_value'] ?? ($d['address']['value'] ?? null),
                'active' => ($d['state']['status'] ?? 'ACTIVE') === 'ACTIVE',
            ];
            if (! $party['inn']) {
                continue;
            }
            $key = $party['inn'].'-'.($party['kpp'] ?? '0');
            Cache::put('dadata:party:'.$key, $party, now()->addHours(2));

            $label = $party['name'].' · ИНН '.$party['inn'];
            if ($party['address']) {
                $label .= ' · '.$party['address'];
            }
            if (! $party['active']) {
                $label .= ' · ЛИКВИДИРОВАНА';
            }
            $out[$key] = $label;
        }

        return $out;
    }

    /** Реквизиты организации, выбранной из подсказок */
    public static function party(?string $key): ?array
    {
        return $key ? Cache::get('dadata:party:'.$key) : null;
    }

    /** @return array<string, string> адрес → адрес */
    public static function addresses(string $query): array
    {
        $out = [];
        foreach (static::suggest('address', $query) as $s) {
            $v = $s['value'] ?? null;
            if ($v) {
                $out[$v] = $v;
            }
        }

        return $out;
    }
}
