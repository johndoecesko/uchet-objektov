<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Resources\AdvanceResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AdvancesRelationManager extends RelationManager
{
    protected static string $relationship = 'advances';

    protected static ?string $title = 'Подотчёт: выдачи и возвраты';

    protected static ?string $modelLabel = 'операцию';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(AdvanceResource::formFields(withEmployee: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(AdvanceResource::tableColumns(withEmployee: false))
            ->filters([\App\Filament\Support\Fields::periodFilter()])
            ->defaultSort('date', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
