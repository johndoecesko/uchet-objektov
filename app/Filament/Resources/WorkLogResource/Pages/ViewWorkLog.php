<?php

namespace App\Filament\Resources\WorkLogResource\Pages;

use App\Filament\Resources\WorkLogResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Просмотр записи (форма только для чтения) — для тех, кому правка недоступна, например в демо */
class ViewWorkLog extends ViewRecord
{
    protected static string $resource = WorkLogResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
