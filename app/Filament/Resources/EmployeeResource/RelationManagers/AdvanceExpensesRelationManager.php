<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Resources\ExpenseResource;
use App\Filament\Support\Fields;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AdvanceExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'advanceExpenses';

    protected static ?string $title = 'Подотчёт: потрачено (чеки)';

    protected static ?string $modelLabel = 'расход';

    public function isReadOnly(): bool
    {
        return true; // только просмотр: расходы из подотчёта вносятся в разделе «Расходы»
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(ExpenseResource::formFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(ExpenseResource::tableColumns())
            ->filters([Fields::periodFilter()])
            ->defaultSort('date', 'desc');
    }
}
