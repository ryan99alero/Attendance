<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\RoundingRule;
use App\Models\Department;
use App\Models\PayrollFrequency;
use App\Models\ShiftSchedule;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Employees';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('first_name')
                ->label('First Name')
                ->required(),
            TextInput::make('last_name')
                ->label('Last Name')
                ->required(),
            TextInput::make('address')
                ->label('Address')
                ->nullable(),
            TextInput::make('city')
                ->label('City')
                ->nullable(),
            TextInput::make('state')
                ->label('State')
                ->nullable(),
            TextInput::make('zip')
                ->label('ZIP Code')
                ->nullable(),
            TextInput::make('country')
                ->label('Country')
                ->nullable(),
            TextInput::make('phone')
                ->label('Phone')
                ->nullable(),
            TextInput::make('external_id')
                ->label('External ID')
                ->nullable(),
            Select::make('department_id')
                ->label('Department')
                ->options(Department::all()->pluck('name', 'id'))
                ->nullable()
                ->searchable(),
            Select::make('payroll_frequency_id')
                ->label('Payroll Frequency')
                ->options(PayrollFrequency::all()->pluck('frequency_name', 'id'))
                ->nullable()
                ->searchable(),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            Select::make('rounding_method')
                ->label('Rounding Method')
                ->options(RoundingRule::all()->pluck('name', 'id'))
                ->nullable(),
            DatePicker::make('termination_date')
                ->label('Termination Date')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('full_names')
                ->label('Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('department.name')
                ->label('Department')
                ->sortable(),
            TextColumn::make('payrollFrequency.frequency_name')
                ->label('Payroll Frequency')
                ->sortable(),
            TextColumn::make('termination_date')
                ->label('Termination Date')
                ->date(),
            IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),
        ]);
    }

    public static function saving($employee): void
    {
        // Ensure the association between employee and user is updated
        if ($employee->user_id) {
            // Clear any previous associations with this employee
            User::query()->where('employee_id', $employee->id)
                ->update(['employee_id' => null]);

            // Set the new association
            User::query()->where('id', $employee->user_id)
                ->update(['employee_id' => $employee->id]);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
