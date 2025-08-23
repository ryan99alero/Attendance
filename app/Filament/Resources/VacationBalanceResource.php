<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\VacationBalanceResource\Pages\ListVacationBalances;
use App\Filament\Resources\VacationBalanceResource\Pages\CreateVacationBalance;
use App\Filament\Resources\VacationBalanceResource\Pages\EditVacationBalance;
use App\Filament\Resources\VacationBalanceResource\Pages;
use App\Models\VacationBalance;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VacationBalanceResource extends Resource
{
    protected static ?string $model = VacationBalance::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Vacation Balances';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required(),
            TextInput::make('accrual_rate')->label('Accrual Rate')->numeric()->required(),
            TextInput::make('accrued_hours')->label('Accrued Hours')->numeric()->required(),
            TextInput::make('used_hours')->label('Used Hours')->numeric()->required(),
            TextInput::make('carry_over_hours')->label('Carry Over Hours')->numeric()->nullable(),
            TextInput::make('cap_hours')->label('Cap Hours')->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employee.first_name')->label('Employee'),
            TextColumn::make('accrual_rate')->label('Accrual Rate'),
            TextColumn::make('accrued_hours')->label('Accrued Hours'),
            TextColumn::make('used_hours')->label('Used Hours'),
            TextColumn::make('carry_over_hours')->label('Carry Over Hours'),
            TextColumn::make('cap_hours')->label('Cap Hours'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVacationBalances::route('/'),
            'create' => CreateVacationBalance::route('/create'),
            'edit' => EditVacationBalance::route('/{record}/edit'),
        ];
    }
}
