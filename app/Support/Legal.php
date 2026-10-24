<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/** Реквизиты оператора для документов (config/legal.php). Не заполнено — заметная пометка «заполнить» */
class Legal
{
    public static function operator(string $key, string $label): HtmlString
    {
        $value = config('legal.operator.'.$key);

        return new HtmlString(filled($value)
            ? e($value)
            : '<mark class="todo">[заполнить: '.e($label).']</mark>');
    }

    public static function complete(): bool
    {
        return collect(config('legal.operator'))->every(fn ($v) => filled($v));
    }

    /** Почта для обращений по персональным данным — ссылкой */
    public static function email(): HtmlString
    {
        $email = config('legal.operator.email');

        return filled($email)
            ? new HtmlString('<a href="mailto:'.e($email).'">'.e($email).'</a>')
            : self::operator('email', 'почта для обращений');
    }

    public static function versionDate(): string
    {
        return Carbon::parse(config('legal.version'))->format('d.m.Y');
    }
}
