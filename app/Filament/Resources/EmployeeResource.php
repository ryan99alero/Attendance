<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Shift;
use App\Models\PayrollFrequency;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Facades\Log;

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
                ->label('Address'),
            TextInput::make('city')
                ->label('City'),
            TextInput::make('state')
                ->label('State'),
            TextInput::make('zip')
                ->label('ZIP Code'),
            TextInput::make('country')
                ->label('Country'),
            TextInput::make('phone')
                ->label('Phone'),
            TextInput::make('external_id')
                ->label('External ID'),
            Select::make('department_id')
                ->label('Department')
                ->options(Department::all()->pluck('name', 'id'))
                ->nullable()
                ->searchable(),
            Select::make('shift_id')
                ->label('Shift')
                ->options(Shift::all()->pluck('shift_name', 'id'))
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
            TextInput::make('normal_hrs_per_day')
                ->label('Normal Hours Per Day')
                ->numeric(),
            Toggle::make('paid_lunch')
                ->label('Paid Lunch'),
            FileUpload::make('photograph')
                ->label('Photograph')
                ->directory('employee_photos')
                ->image()
                ->nullable(),
            DatePicker::make('start_date')
                ->label('Start Date')
                ->nullable(),
            TimePicker::make('start_time')
                ->label('Start Time')
                ->nullable(),
            TimePicker::make('stop_time')
                ->label('Stop Time')
                ->nullable(),
            DatePicker::make('termination_date')
                ->label('Termination Date')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('full_name')
                ->label('Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('department.name')
                ->label('Department')
                ->sortable(),
            TextColumn::make('shift.shift_name')
                ->label('Shift')
                ->sortable(),
            TextColumn::make('payrollFrequency.frequency_name')
                ->label('Payroll Frequency')
                ->sortable(),
            TextColumn::make('phone')
                ->label('Phone'),
            IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),
        ]);
    }

    public static function saving($employee)
    {
        // Ensure the association between employee and user is updated
        if ($employee->user_id) {
            // Clear any previous associations with this employee
            User::where('employee_id', $employee->id)
                ->update(['employee_id' => null]);

            // Set the new association
            User::where('id', $employee->user_id)
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
