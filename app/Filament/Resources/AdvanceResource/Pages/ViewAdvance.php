<?php

namespace App\Filament\Resources\AdvanceResource\Pages;

use App\Filament\Resources\AdvanceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Просмотр записи (форма только для чтения) — для тех, кому правка недоступна, например в демо */
class ViewAdvance extends ViewRecord
{
    protected static string $resource = AdvanceResource::class;

    /** Свой заголовок: Filament склеил бы «Просмотр» с подписью модели в винительном падеже */
    public function getTitle(): string
    {
        return 'Операция подотчёта';
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
