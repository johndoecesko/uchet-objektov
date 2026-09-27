<?php

namespace App\Filament\Resources\PayoutResource\Pages;

use App\Filament\Resources\PayoutResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayout extends CreateRecord
{
    protected static string $resource = PayoutResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
