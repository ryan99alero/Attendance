<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use App\Filament\Resources\EmployeeStatResource\Pages\ListEmployeeStats;
use App\Filament\Resources\EmployeeStatResource\Pages\CreateEmployeeStat;
use App\Filament\Resources\EmployeeStatResource\Pages\EditEmployeeStat;
use UnitEnum;
use BackedEnum;

use App\Filament\Resources\EmployeeStatResource\Pages;
use App\Models\EmployeeStat;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class EmployeeStatResource extends Resource
{
    protected static ?string $model = EmployeeStat::class;

    // Navigation Configuration
    protected static string | \UnitEnum | null $navigationGroup = 'Reports & Analytics';
    protected static ?string $navigationLabel = 'Employee Stats';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
            'index' => ListEmployeeStats::route('/'),
            'create' => CreateEmployeeStat::route('/create'),
            'edit' => EditEmployeeStat::route('/{record}/edit'),
        ];
    }
}
