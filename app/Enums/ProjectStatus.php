<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasLabel
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => 'Планируется',
            self::Active => 'В работе',
            self::Completed => 'Завершён',
            self::Archived => 'Архив',
        };
    }
}
