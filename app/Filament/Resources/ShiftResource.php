<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use App\Filament\Resources\ShiftResource\Pages\ListShifts;
use App\Filament\Resources\ShiftResource\Pages\CreateShift;
use App\Filament\Resources\ShiftResource\Pages\EditShift;
use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Shifts';

    /**
     * Define the form schema for creating/editing records.
     *
     * @param \Filament\Schemas\Schema $schema
     * @return \Filament\Schemas\Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
                ->falseIcon('heroicon-o-x'),
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
            'index' => ListShifts::route('/'),
            'create' => CreateShift::route('/create'),
            'edit' => EditShift::route('/{record}/edit'),
        ];
    }
}
