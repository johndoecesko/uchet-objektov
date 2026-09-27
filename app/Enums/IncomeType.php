<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IncomeType: string implements HasLabel
{
    case Advance = 'advance';
    case Stage = 'stage';
    case Final = 'final';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Advance => 'Аванс',
            self::Stage => 'Оплата этапа',
            self::Final => 'Окончательный расчёт',
            self::Other => 'Прочее',
        };
    }
}
