<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PunchTypeResource\Pages\CreatePunchType;
use App\Filament\Resources\PunchTypeResource\Pages\EditPunchType;
use App\Filament\Resources\PunchTypeResource\Pages\ListPunchTypes;
use App\Models\PunchType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PunchTypeResource extends Resource
{
    protected static ?string $model = PunchType::class;

    // Navigation Configuration
    protected static string|\UnitEnum|null $navigationGroup = 'Time Tracking';

    protected static ?string $navigationLabel = 'Punch Types';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = -80;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Punch Type Name')
                ->required(),
            Textarea::make('description')
                ->label('Description')
                ->nullable(),
            Select::make('punch_direction')
                ->label('Direction')
                ->options([
                    'start' => 'Start (Clock In / Resume Work)',
                    'stop' => 'Stop (Clock Out / Pause Work)',
                ])
                ->placeholder('None')
                ->nullable()
                ->helperText('Start = beginning work or resuming after break. Stop = ending work or taking a break.'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Punch Type Name'),
            TextColumn::make('description')->label('Description'),
            TextColumn::make('punch_direction')
                ->label('Direction')
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'start' => 'success',
                    'stop' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?string $state): string => match ($state) {
                    'start' => 'Start',
                    'stop' => 'Stop',
                    default => 'None',
                }),
            IconColumn::make('is_active')->label('Active'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPunchTypes::route('/'),
            'create' => CreatePunchType::route('/create'),
            'edit' => EditPunchType::route('/{record}/edit'),
        ];
    }
}
