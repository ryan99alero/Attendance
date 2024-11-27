<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Models\Device;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-desktop-computer';
    protected static ?string $navigationLabel = 'Devices';

    public static function form(Form $form): Form
    {
        return $form->schema([
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
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
