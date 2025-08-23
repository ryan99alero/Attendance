<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use App\Filament\Resources\PunchTypeResource\Pages\ListPunchTypes;
use App\Filament\Resources\PunchTypeResource\Pages\CreatePunchType;
use App\Filament\Resources\PunchTypeResource\Pages\EditPunchType;
use App\Filament\Resources\PunchTypeResource\Pages;
use App\Models\PunchType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PunchTypeResource extends Resource
{
    protected static ?string $model = PunchType::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Punch Types';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Punch Type Name')
                ->required(),
            Textarea::make('description')
                ->label('Description')
                ->nullable(),
            Select::make('schedule_reference')
                ->label('Schedule')
                ->options([
                    'start_time' => 'Start Time',
                    'lunch_start' => 'Lunch Start',
                    'lunch_stop' => 'Lunch Stop',
                    'stop_time' => 'Stop Time',
                    'flexible' => 'Flexible',
                    'passthrough' => 'Passthrough',
                ])
                ->nullable()
                ->searchable(),
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
            TextColumn::make('schedule_reference')
                ->label('Schedule')
                ->formatStateUsing(function ($state) {
                    return match ($state) {
                        'start_time' => 'Start Time',
                        'lunch_start' => 'Lunch Start',
                        'lunch_stop' => 'Lunch Stop',
                        'stop_time' => 'Stop Time',
                        'flexible' => 'Flexible',
                        'passthrough' => 'Passthrough',
                        default => 'None',
                    };
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
