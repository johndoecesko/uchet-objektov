<?php

namespace App\Filament\Resources\WorkLogResource\Pages;

use App\Filament\Resources\WorkLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkLog extends EditRecord
{
    protected static string $resource = WorkLogResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
