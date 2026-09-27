<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayMethod: string implements HasLabel
{
    case Card = 'card';
    case Cash = 'cash';
    case Bank = 'bank';

    public function getLabel(): string
    {
        return match ($this) {
            self::Card => 'На карту',
            self::Cash => 'Наличными',
            self::Bank => 'Безналичный перевод',
        };
    }
}
