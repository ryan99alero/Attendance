<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacationBalanceResource\Pages;
use App\Models\VacationBalance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VacationBalanceResource extends Resource
{
    protected static ?string $model = VacationBalance::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Vacation Balances';
    protected static ?string $navigationGroup = 'Time Off Management';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required(),
            Forms\Components\TextInput::make('accrual_rate')->label('Accrual Rate')->numeric()->required(),
            Forms\Components\TextInput::make('accrued_hours')->label('Accrued Hours')->numeric()->required(),
            Forms\Components\TextInput::make('used_hours')->label('Used Hours')->numeric()->required(),
            Forms\Components\TextInput::make('carry_over_hours')->label('Carry Over Hours')->numeric()->nullable(),
            Forms\Components\TextInput::make('cap_hours')->label('Cap Hours')->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('employee.first_name')->label('Employee'),
            Tables\Columns\TextColumn::make('accrual_rate')->label('Accrual Rate'),
            Tables\Columns\TextColumn::make('accrued_hours')->label('Accrued Hours'),
            Tables\Columns\TextColumn::make('used_hours')->label('Used Hours'),
            Tables\Columns\TextColumn::make('carry_over_hours')->label('Carry Over Hours'),
            Tables\Columns\TextColumn::make('cap_hours')->label('Cap Hours'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVacationBalances::route('/'),
            'create' => Pages\CreateVacationBalance::route('/create'),
            'edit' => Pages\EditVacationBalance::route('/{record}/edit'),
        ];
    }
}
