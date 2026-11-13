<?php

namespace App\Filament\Resources\PayoutResource\Pages;

use App\Filament\Resources\PayoutResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Просмотр записи (форма только для чтения) — для тех, кому правка недоступна, например в демо */
class ViewPayout extends ViewRecord
{
    protected static string $resource = PayoutResource::class;

    /** Свой заголовок: Filament склеил бы «Просмотр» с подписью модели в винительном падеже */
    public function getTitle(): string
    {
        return 'Выплата';
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
