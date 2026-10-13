<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Widgets\EmployeeStats;
use App\Filament\Widgets\RecordHistory;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [EmployeeStats::class];
    }

    protected function getFooterWidgets(): array
    {
        return [RecordHistory::class];
    }
}
