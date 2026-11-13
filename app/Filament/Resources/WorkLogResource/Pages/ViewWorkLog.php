<?php

namespace App\Filament\Resources\WorkLogResource\Pages;

use App\Filament\Resources\WorkLogResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Просмотр записи (форма только для чтения) — для тех, кому правка недоступна, например в демо */
class ViewWorkLog extends ViewRecord
{
    protected static string $resource = WorkLogResource::class;

    /** Свой заголовок: Filament склеил бы «Просмотр» с подписью модели в винительном падеже */
    public function getTitle(): string
    {
        return 'Запись о работе';
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
