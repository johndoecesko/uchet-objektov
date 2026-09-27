<?php

namespace App\Filament\Resources\WorkLogResource\Pages;

use App\Filament\Resources\WorkLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkLogs extends ListRecords
{
    protected static string $resource = WorkLogResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
