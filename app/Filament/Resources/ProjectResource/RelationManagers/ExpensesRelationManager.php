<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Filament\Resources\ExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Расходы';

    protected static ?string $modelLabel = 'расход';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(ExpenseResource::formFields(withProject: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(ExpenseResource::tableColumns(withProject: false))
            ->filters([\App\Filament\Support\Fields::periodFilter(), \App\Filament\Support\Fields::categoryFilter()])
            ->defaultSort('date', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
