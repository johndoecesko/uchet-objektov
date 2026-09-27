<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentSource: string implements HasLabel
{
    case Bank = 'bank';
    case CompanyCard = 'company_card';
    case Cash = 'cash';
    case Advance = 'advance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bank => 'Расчётный счёт',
            self::CompanyCard => 'Корпоративная карта',
            self::Cash => 'Наличные (касса)',
            self::Advance => 'Из подотчёта сотрудника',
        };
    }
}
