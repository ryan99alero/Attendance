<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
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
                ->tel()
                ->nullable(),
            TextInput::make('external_id')
                ->label('External ID')
                ->nullable(),
            Select::make('department_id')
                ->relationship('department', 'name')
                ->label('Department')
                ->nullable()
                ->placeholder('No departments available'),
            Select::make('shift_id')
                ->relationship('shift', 'shift_name')
                ->label('Shift')
                ->nullable(),
            Select::make('rounding_method')
                ->relationship('roundingMethod', 'name') // Ensure the relationship in the model matches
                ->label('Rounding Method')
                ->nullable(),
            TextInput::make('normal_hrs_per_day')
                ->label('Normal Hours Per Day')
                ->numeric()
                ->nullable(),
            Toggle::make('paid_lunch')
                ->label('Paid Lunch')
                ->default(false),
            TextInput::make('pay_periods_per_year')
                ->label('Pay Periods Per Year')
                ->numeric()
                ->nullable(),
            TextInput::make('photograph')
                ->label('Photograph Path/URL')
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
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('first_name')->label('First Name')->sortable()->searchable(),
            TextColumn::make('last_name')->label('Last Name')->sortable()->searchable(),
            TextColumn::make('address')->label('Address')->limit(50)->wrap(),
            TextColumn::make('city')->label('City'),
            TextColumn::make('state')->label('State'),
            TextColumn::make('zip')->label('ZIP Code'),
            TextColumn::make('country')->label('Country'),
            TextColumn::make('phone')->label('Phone'),
            TextColumn::make('external_id')->label('External ID'),
            TextColumn::make('department.name')->label('Department'),
            TextColumn::make('shift.shift_name')->label('Shift'),
            TextColumn::make('normal_hrs_per_day')->label('Hours/Day'),
            IconColumn::make('paid_lunch')->label('Paid Lunch')->boolean(),
            TextColumn::make('pay_periods_per_year')->label('Pay Periods/Year'),
            TextColumn::make('start_date')->label('Start Date')->date(),
            TextColumn::make('termination_date')->label('Termination Date')->date(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
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
