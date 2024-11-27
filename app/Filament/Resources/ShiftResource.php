<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Shifts';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('shift_name')->label('Shift Name')->required(),
            Forms\Components\TimePicker::make('start_time')->label('Start Time')->required(),
            Forms\Components\TimePicker::make('end_time')->label('End Time')->required(),
            Forms\Components\TextInput::make('base_hours_per_period')
                ->label('Base Hours Per Period')
                ->numeric()
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('shift_name')->label('Shift Name'),
            Tables\Columns\TimeColumn::make('start_time')->label('Start Time'),
            Tables\Columns\TimeColumn::make('end_time')->label('End Time'),
            Tables\Columns\TextColumn::make('base_hours_per_period')->label('Base Hours Per Period'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
        ];
    }
}
