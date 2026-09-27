<?php

namespace App\Filament\Resources\AdvanceResource\Pages;

use App\Filament\Resources\AdvanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvance extends CreateRecord
{
    protected static string $resource = AdvanceResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
