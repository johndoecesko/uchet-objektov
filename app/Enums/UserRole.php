<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Foreman = 'foreman';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::Manager => 'Менеджер / бухгалтер',
            self::Foreman => 'Прораб / монтажник',
        };
    }
}
