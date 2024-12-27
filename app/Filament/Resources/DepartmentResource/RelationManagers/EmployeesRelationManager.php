<?php

namespace App\Filament\Resources\DepartmentResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Models\Employee;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $label = 'Employee';
    protected static ?string $pluralLabel = 'Employees';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->label('First Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Last Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Existing Employee')
                    ->form([
                        Forms\Components\Select::make('employee_id')
                            ->label('Employee')
                            ->options(function () {
                                return Employee::whereNull('department_id')
                                    ->get()
                                    ->pluck('full_name', 'id'); // Assumes Employee has a `full_name` accessor
                            })
                            ->searchable()
                            ->required()
                            ->placeholder('Select an employee without a department'),
                    ])
                    ->action(function (array $data) {
                        // Assign the selected employee to this department
                        $employee = Employee::findOrFail($data['employee_id']);
                        $employee->department_id = $this->ownerRecord->id;
                        $employee->save();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-user-remove')
                    ->action(function ($record) {
                        // Set the department_id to null instead of deleting the employee
                        $record->department_id = null;
                        $record->save();
                    })
                    ->requiresConfirmation()
                    ->color('warning'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkRemove')
                    ->label('Remove from Department')
                    ->icon('heroicon-o-user-remove')
                    ->action(function ($records) {
                        // Set department_id to null for all selected records
                        foreach ($records as $record) {
                            $record->department_id = null;
                            $record->save();
                        }
                    })
                    ->requiresConfirmation()
                    ->color('warning'),
            ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('first_name')
                ->label('First Name')
                ->required(),
            Forms\Components\TextInput::make('last_name')
                ->label('Last Name')
                ->required(),
            Forms\Components\TextInput::make('email')
                ->label('eMail')
                ->email(),
        ]);
    }
}
