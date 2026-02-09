<?php

namespace App\Filament\Resources\ScheduleResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Assigned Employees';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_names')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('external_id')
                    ->label('Payroll ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.department_name')
                    ->label('Department')
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('full_names')
            ->paginated([10, 25, 50])
            ->striped();
    }
}
