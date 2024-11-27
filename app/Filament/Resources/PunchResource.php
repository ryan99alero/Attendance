<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PunchResource\Pages;
use App\Models\Punch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PunchResource extends Resource
{
    protected static ?string $model = Punch::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Punches';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required(),
            Forms\Components\Select::make('device_id')
                ->relationship('device', 'device_name')
                ->label('Device')
                ->nullable(),
            Forms\Components\Select::make('punch_type_id')
                ->relationship('punchType', 'name')
                ->label('Punch Type')
                ->nullable(),
            Forms\Components\DateTimePicker::make('time_in')->label('Punch In')->required(),
            Forms\Components\DateTimePicker::make('time_out')->label('Punch Out')->nullable(),
            Forms\Components\Toggle::make('is_altered')->label('Altered')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('employee.first_name')->label('Employee'),
            Tables\Columns\TextColumn::make('device.device_name')->label('Device'),
            Tables\Columns\TextColumn::make('punchType.name')->label('Punch Type'),
            Tables\Columns\TextColumn::make('time_in')->label('Punch In'),
            Tables\Columns\TextColumn::make('time_out')->label('Punch Out'),
            Tables\Columns\IconColumn::make('is_altered')->label('Altered'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPunches::route('/'),
            'create' => Pages\CreatePunch::route('/create'),
            'edit' => Pages\EditPunch::route('/{record}/edit'),
        ];
    }
}
