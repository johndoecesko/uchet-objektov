<?php

namespace App\Filament\Resources\IncomeResource\Pages;

use App\Filament\Resources\IncomeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Просмотр записи (форма только для чтения) — для тех, кому правка недоступна, например в демо */
class ViewIncome extends ViewRecord
{
    protected static string $resource = IncomeResource::class;

    /** Свой заголовок: Filament склеил бы «Просмотр» с подписью модели в винительном падеже */
    public function getTitle(): string
    {
        return 'Поступление';
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
