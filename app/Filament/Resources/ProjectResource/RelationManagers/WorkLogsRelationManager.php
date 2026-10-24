<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Filament\Resources\WorkLogResource;
use App\Filament\Support\Fields;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WorkLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'workLogs';

    protected static ?string $title = 'Журнал работ';

    protected static ?string $modelLabel = 'запись о работе';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(WorkLogResource::formFields(withProject: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(WorkLogResource::tableColumns(withProject: false))
            ->filters([Fields::periodFilter(), Fields::employeeFilter()])
            ->defaultSort('date', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
