<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrganizationStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Blocked = 'blocked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Работает',
            self::Blocked => 'Заблокирована',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Blocked => 'danger',
        };
    }
}
