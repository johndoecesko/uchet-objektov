<?php

namespace App\Filament\Resources\WorkLogResource\Pages;

use App\Filament\Resources\WorkLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkLog extends CreateRecord
{
    protected static string $resource = WorkLogResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
