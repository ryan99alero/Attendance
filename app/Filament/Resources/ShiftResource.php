<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Shifts';

    /**
     * Define the form schema for creating/editing records.
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('shift_name')
                ->label('Shift Name')
                ->required()
                ->maxLength(100),

            TimePicker::make('start_time')
                ->label('Start Time')
                ->required(),

            TimePicker::make('end_time')
                ->label('End Time')
                ->required(),

            TextInput::make('base_hours_per_period')
                ->label('Base Hours Per Period')
                ->numeric()
                ->nullable(),

            Toggle::make('multi_day_shift')
                ->label('Multi-Day Shift')
                ->helperText('Enable if this shift crosses midnight.')
                ->default(false),
        ]);
    }

    /**
     * Define the table schema for listing records.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('shift_name')
                ->label('Shift Name')
                ->sortable()
                ->searchable(),

            TextColumn::make('start_time')
                ->label('Start Time')
                ->time('H:i'),

            TextColumn::make('end_time')
                ->label('End Time')
                ->time('H:i'),

            TextColumn::make('base_hours_per_period')
                ->label('Base Hours Per Period'),

            BooleanColumn::make('multi_day_shift')
                ->label('Multi-Day Shift')
                ->trueIcon('heroicon-o-check')
                ->falseIcon('heroicon-o-x-circle'),
        ]);
    }

    /**
     * Define the available pages for this resource.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
        ];
    }
}
