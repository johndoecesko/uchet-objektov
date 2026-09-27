<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdvanceType: string implements HasLabel
{
    case Issue = 'issue';
    case Return = 'return';

    public function getLabel(): string
    {
        return match ($this) {
            self::Issue => 'Выдано под отчёт',
            self::Return => 'Возврат остатка',
        };
    }
}
