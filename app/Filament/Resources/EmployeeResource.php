<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\Department;
use App\Models\PayrollFrequency;
use App\Models\Shift; // Added for shift_id dropdown
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Employees';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Basic personal information
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

            // Address fields
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

            // Employment and organizational details
            Select::make('department_id')
                ->label('Department')
                ->options(Department::all()->pluck('name', 'id'))
                ->nullable()
                ->searchable(),
            Select::make('shift_id')
                ->label('Shift')
                ->options(Shift::all()->pluck('shift_name', 'id')) // Dropdown for shifts
                ->nullable()
                ->searchable(),
            Select::make('payroll_frequency_id')
                ->label('Payroll Frequency')
                ->options(PayrollFrequency::all()->pluck('frequency_name', 'id'))
                ->nullable()
                ->searchable(),
            Select::make('round_group_id')
                ->label('Payroll Rounding')
                ->options(\App\Models\RoundGroup::all()->pluck('group_name', 'id')) // Use the correct model and fields
                ->nullable()
                ->searchable(),

            // Flags and identifiers
            TextInput::make('external_id')
                ->label('External ID')
                ->nullable(),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            Toggle::make('full_time')
                ->label('Full Time')
                ->default(false),
            Toggle::make('vacation_pay')
                ->label('Vacation Pay')
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
                TextColumn::make('shift.shift_name') // Ensure the relationship is defined in the Employee model
                ->label('Shift')
                    ->sortable(),
                TextColumn::make('payrollFrequency.frequency_name')
                    ->label('Payroll Frequency')
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
