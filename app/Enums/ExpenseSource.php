<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Откуда пришёл расход: из веб-интерфейса или от бота в MAX */
enum ExpenseSource: string implements HasLabel
{
    case Web = 'web';
    case Max = 'max';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Сайт',
            self::Max => 'MAX',
        };
    }
}
