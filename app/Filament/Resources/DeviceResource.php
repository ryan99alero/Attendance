<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use App\Filament\Resources\DeviceResource\Pages\ListDevices;
use App\Filament\Resources\DeviceResource\Pages\CreateDevice;
use App\Filament\Resources\DeviceResource\Pages\EditDevice;
use App\Filament\Resources\DeviceResource\Pages;
use App\Models\Device;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-desktop-computer';
    protected static ?string $navigationLabel = 'Devices';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('device_name')->label('Device Name')->required(),
            TextInput::make('ip_address')->label('IP Address')->nullable(),
            Select::make('department_id')
                ->relationship('department', 'name')
                ->label('Department'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('device_name')->label('Device Name'),
            TextColumn::make('ip_address')->label('IP Address'),
            TextColumn::make('department.name')->label('Department'),
            IconColumn::make('is_active')->label('Active'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevices::route('/'),
            'create' => CreateDevice::route('/create'),
            'edit' => EditDevice::route('/{record}/edit'),
        ];
    }
}
