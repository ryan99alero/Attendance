<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PunchResource\Pages;
use App\Models\Punch;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;

class PunchResource extends Resource
{
    protected static ?string $model = Punch::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Punches';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required(),
            Select::make('device_id')
                ->relationship('device', 'device_name')
                ->label('Device')
                ->nullable(),
            Select::make('punch_type_id')
                ->relationship('punchType', 'name')
                ->label('Punch Type')
                ->nullable(),
            DateTimePicker::make('time_in')
                ->label('Punch In')
                ->required(),
            DateTimePicker::make('time_out')
                ->label('Punch Out')
                ->nullable(),
            Toggle::make('is_altered')
                ->label('Altered'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employee.first_name')
                ->label('Employee')
                ->alignCenter()
                ->sortable()
                ->searchable(),

            TextColumn::make('device.device_name')
                ->label('Device')
                ->alignCenter()
                ->sortable()
                ->searchable(),

            TextColumn::make('punchType.name')
                ->label('Punch Type')
                ->alignCenter()
                ->sortable()
                ->searchable(),

            TextInputColumn::make('time_in')
                ->label('Punch In')
                ->alignCenter()
                ->rules(['required', 'date_format:Y-m-d H:i:s'])
                ->afterStateUpdated(fn ($state, $record) => $record->update(['time_in' => $state]))
                ->placeholder('YYYY-MM-DD HH:MM:SS')
                ->searchable(),

            TextInputColumn::make('time_out')
                ->label('Punch Out')
                ->alignCenter()
                ->rules(['nullable', 'date_format:Y-m-d H:i:s'])
                ->afterStateUpdated(fn ($state, $record) => $record->update(['time_out' => $state]))
                ->placeholder('YYYY-MM-DD HH:MM:SS')
                ->searchable(),

            IconColumn::make('is_altered')
                ->label('Altered')
                ->alignCenter()
                ->boolean(),
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
