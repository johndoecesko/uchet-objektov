<?php

namespace App\Support;

class Money
{
    public static function fmt(float|int|string|null $value, bool $sign = false): string
    {
        $v = (float) ($value ?? 0);
        $s = number_format(abs($v), 2, ',', "\u{00A0}");
        $s = str_ends_with($s, ',00') ? substr($s, 0, -3) : $s;
        $prefix = $v < 0 ? '−' : ($sign && $v > 0 ? '+' : '');

        return $prefix.$s."\u{00A0}₽";
    }

    /** Цвет освоения бюджета: до 80% — нейтральный, 80–100% — оранжевый, больше 100% — красный */
    public static function budgetColor(int|float|null $percent): string
    {
        return match (true) {
            $percent === null => 'gray',
            $percent > 100 => 'danger',
            $percent >= 80 => 'warning',
            default => 'gray',
        };
    }

    public static function pct(float $part, float $total): string
    {
        return $total != 0.0 ? number_format($part / $total * 100, 1, ',', '').'%' : '—';
    }
}
