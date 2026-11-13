<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Просмотр записи (форма только для чтения) — для тех, кому правка недоступна, например в демо */
class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    /** Свой заголовок: Filament склеил бы «Просмотр» с подписью модели в винительном падеже */
    public function getTitle(): string
    {
        return 'Расход';
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
