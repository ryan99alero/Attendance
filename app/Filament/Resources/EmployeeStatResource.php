<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeStatResource\Pages;
use App\Models\EmployeeStat;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class EmployeeStatResource extends Resource
{
    protected static ?string $model = EmployeeStat::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Employee Stats';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required(),
            TextInput::make('hours_worked')
                ->label('Hours Worked')
                ->numeric()
                ->required(),
            TextInput::make('overtime_hours')
                ->label('Overtime Hours')
                ->numeric()
                ->required(),
            TextInput::make('leave_days')
                ->label('Leave Days')
                ->numeric()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employee.first_name')
                ->label('Employee')
                ->searchable(),
            TextColumn::make('hours_worked')
                ->label('Hours Worked'),
            TextColumn::make('overtime_hours')
                ->label('Overtime Hours'),
            TextColumn::make('leave_days')
                ->label('Leave Days'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeStats::route('/'),
            'create' => Pages\CreateEmployeeStat::route('/create'),
            'edit' => Pages\EditEmployeeStat::route('/{record}/edit'),
        ];
    }
}
