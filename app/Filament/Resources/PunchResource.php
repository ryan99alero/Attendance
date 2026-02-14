<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PunchResource\Pages\CreatePunch;
use App\Filament\Resources\PunchResource\Pages\EditPunch;
use App\Filament\Resources\PunchResource\Pages\ListPunches;
use App\Models\Punch;
use Exception;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class PunchResource extends Resource
{
    protected static ?string $model = Punch::class;

    // Navigation Configuration
    protected static string|\UnitEnum|null $navigationGroup = 'Time Tracking';

    protected static ?string $navigationLabel = 'Punches';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static ?int $navigationSort = -90;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
            DateTimePicker::make('punch_time')
                ->label('Punch In')
                ->required(),
            Toggle::make('is_altered')
                ->label('Altered'),
        ]);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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

                TextInputColumn::make('punch_time')
                    ->label('Punch In')
                    ->alignCenter()
                    ->rules(['required', 'date_format:Y-m-d H:i:s'])
                    ->placeholder('YYYY-MM-DD HH:MM')
                    ->afterStateUpdated(fn ($state, $record) => $record->update(['punch_time' => $state]))
                    ->searchable(),

                IconColumn::make('is_altered')
                    ->label('Altered')
                    ->alignCenter()
                    ->boolean(),
            ])
            ->emptyStateHeading('No Pay Period Selected')
            ->emptyStateDescription('Click the "Pay Period" button above to select a posted pay period.')
            ->emptyStateIcon('heroicon-o-finger-print');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPunches::route('/'),
            'create' => CreatePunch::route('/create'),
            'edit' => EditPunch::route('/{record}/edit'),
        ];
    }
}
