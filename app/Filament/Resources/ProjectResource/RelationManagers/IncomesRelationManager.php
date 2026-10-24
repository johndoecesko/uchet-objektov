<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Filament\Resources\IncomeResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IncomesRelationManager extends RelationManager
{
    protected static string $relationship = 'incomes';

    protected static ?string $title = 'Поступления';

    protected static ?string $modelLabel = 'поступление';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(IncomeResource::formFields(withProject: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(IncomeResource::tableColumns(withProject: false))
            ->filters([\App\Filament\Support\Fields::periodFilter()])
            ->defaultSort('date', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
