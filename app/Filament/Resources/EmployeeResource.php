<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Shift; // Added for shift_id dropdown
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    // Navigation Configuration
    protected static ?string $navigationGroup = 'Employee Management';
    protected static ?string $navigationLabel = 'Employees';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Basic Personal Information
            TextInput::make('first_name')
                ->label('First Name')
                ->required(),
            TextInput::make('last_name')
                ->label('Last Name')
                ->required(),
            TextInput::make('email')
                ->label('Email')
                ->nullable(),
            TextInput::make('phone')
                ->label('Phone')
                ->nullable(),
            TextInput::make('external_id')
                ->label('External ID')
                ->nullable(),

            // Address Information
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

            // Employment Dates
            DatePicker::make('date_of_hire')
                ->label('Date of Hire')
                ->nullable(),
            DatePicker::make('seniority_date')
                ->label('Seniority Date')
                ->nullable(),
            DatePicker::make('termination_date')
                ->label('Termination Date')
                ->nullable(),

            // Organizational Assignment
            Select::make('department_id')
                ->label('Department')
                ->options(Department::all()->pluck('name', 'id'))
                ->nullable()
                ->searchable(),
            Select::make('shift_schedule_id')
                ->label('Shift Schedule')
                ->options(\App\Models\ShiftSchedule::with(['shift'])
                    ->get()
                    ->mapWithKeys(function ($schedule) {
                        $shiftName = $schedule->shift ? $schedule->shift->shift_name : 'No Shift';
                        return [$schedule->id => "{$schedule->schedule_name} ({$shiftName})"];
                    })
                    ->toArray())
                ->searchable()
                ->nullable(),
            Select::make('round_group_id')
                ->label('Payroll Rounding')
                ->options(\App\Models\RoundGroup::all()->pluck('group_name', 'id')) // Use the correct model and fields
                ->nullable()
                ->searchable(),

            // Pay Information
            Select::make('pay_type')
                ->label('Pay Type')
                ->options([
                    'hourly' => 'Hourly',
                    'salary' => 'Salary',
                    'contract' => 'Contract',
                ])
                ->default('hourly'),
            TextInput::make('pay_rate')
                ->label('Pay Rate')
                ->numeric()
                ->step(0.01)
                ->nullable(),

            // Overtime Settings
            TextInput::make('overtime_rate')
                ->label('Overtime Rate Multiplier')
                ->numeric()
                ->step(0.001)
                ->default(1.500),
            TextInput::make('double_time_threshold')
                ->label('Double Time Threshold (Hours)')
                ->numeric()
                ->step(0.01)
                ->nullable(),

            // Note: Vacation management is now handled through the configurable vacation system

            // Status Flags
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            Toggle::make('full_time')
                ->label('Full Time')
                ->default(false),
            Toggle::make('overtime_exempt')
                ->label('Overtime Exempt')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_names')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable(),
                TextColumn::make('shiftSchedule.schedule_name')
                    ->label('Schedule')
                    ->sortable(),
                TextColumn::make('shift.shift_name') // Through relationship
                    ->label('Shift')
                    ->sortable(),
                TextColumn::make('roundGroup.group_name') // Ensure the relationship is defined in the Employee model
                ->label('Payroll Rounding')
                    ->sortable(),
                TextColumn::make('external_id')
                    ->label('Payroll ID')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ]);
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
