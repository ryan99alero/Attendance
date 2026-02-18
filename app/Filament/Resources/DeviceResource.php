<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages\CreateDevice;
use App\Filament\Resources\DeviceResource\Pages\EditDevice;
use App\Filament\Resources\DeviceResource\Pages\ListDevices;
use App\Models\Device;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    // Navigation Configuration
    protected static string|\UnitEnum|null $navigationGroup = 'System & Hardware';

    protected static ?string $navigationLabel = 'Devices';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basic Device Information')
                ->schema([
                    TextInput::make('device_name')
                        ->label('Device Name')
                        ->required()
                        ->helperText('Internal device identifier'),

                    TextInput::make('display_name')
                        ->label('Display Name')
                        ->nullable()
                        ->helperText('Human-friendly name (e.g., "Front Office Clock")'),

                    TextInput::make('device_id')
                        ->label('Device ID')
                        ->disabled()
                        ->helperText('Auto-generated unique identifier'),

                    TextInput::make('mac_address')
                        ->label('MAC Address')
                        ->disabled()
                        ->helperText('Hardware MAC address'),

                    Select::make('department_id')
                        ->relationship('department', 'name')
                        ->label('Department')
                        ->nullable(),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('Time Clock Configuration')
                ->schema([
                    Select::make('timezone')
                        ->label('Timezone')
                        ->options([
                            'Alaska Time (AKST/AKDT)' => 'Alaska Time (AKST/AKDT)',
                            'Atlantic Time (AST)' => 'Atlantic Time (AST)',
                            'Central Time (CST/CDT)' => 'Central Time (CST/CDT)',
                            'Chamorro Time (ChST)' => 'Chamorro Time (ChST)',
                            'Eastern Time (EST/EDT)' => 'Eastern Time (EST/EDT)',
                            'Hawaii-Aleutian Time (HST/HDT)' => 'Hawaii-Aleutian Time (HST/HDT)',
                            'Mountain Time (MST/MDT)' => 'Mountain Time (MST/MDT)',
                            'Pacific Time (PST/PDT)' => 'Pacific Time (PST/PDT)',
                            'Samoa Time (SST)' => 'Samoa Time (SST)',
                        ])
                        ->nullable()
                        ->helperText('Device timezone for time recording'),

                    TextInput::make('ntp_server')
                        ->label('NTP Server')
                        ->placeholder('pool.ntp.org')
                        ->helperText('Leave empty to use default NTP servers'),

                    Select::make('device_type')
                        ->label('Device Type')
                        ->options([
                            'esp32_timeclock' => 'ESP32 Time Clock',
                            'network_scanner' => 'Network Scanner',
                            'mobile_app' => 'Mobile App',
                            'web_browser' => 'Web Browser',
                        ])
                        ->default('esp32_timeclock'),
                ])
                ->columns(2),

            Section::make('Network & Status Information')
                ->schema([
                    TextInput::make('ip_address')
                        ->label('Current IP Address')
                        ->disabled()
                        ->helperText('Last known IP address'),

                    TextInput::make('last_ip')
                        ->label('Last IP')
                        ->disabled()
                        ->helperText('Previous IP address'),

                    TextInput::make('firmware_version')
                        ->label('Firmware Version')
                        ->disabled()
                        ->helperText('Current firmware version'),

                    Select::make('registration_status')
                        ->label('Registration Status')
                        ->options([
                            'pending' => 'Pending Approval',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'suspended' => 'Suspended',
                        ])
                        ->default('pending'),

                    TextInput::make('last_seen_at')
                        ->label('Last Seen')
                        ->disabled()
                        ->helperText('Last heartbeat from device'),

                    TextInput::make('offline_alerted_at')
                        ->label('Offline Alert Sent')
                        ->disabled()
                        ->helperText('When offline alert was sent (empty = online)'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            IconColumn::make('offline_alerted_at')
                ->label('Alert')
                ->icon(fn ($state) => $state ? 'heroicon-s-exclamation-triangle' : null)
                ->color('danger')
                ->tooltip(fn ($state) => $state ? 'Offline alert sent' : null)
                ->alignCenter(),

            TextColumn::make('device_name')
                ->label('Device Name')
                ->searchable(),

            TextColumn::make('display_name')
                ->label('Display Name')
                ->searchable()
                ->placeholder('No display name'),

            TextColumn::make('device_type')
                ->label('Type')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'esp32_timeclock' => 'success',
                    'network_scanner' => 'info',
                    'mobile_app' => 'warning',
                    'web_browser' => 'gray',
                    default => 'gray',
                }),

            TextColumn::make('mac_address')
                ->label('MAC Address')
                ->searchable()
                ->copyable(),

            TextColumn::make('ip_address')
                ->label('IP Address')
                ->placeholder('Not connected'),

            TextColumn::make('timezone')
                ->label('Timezone')
                ->placeholder('Not set'),

            TextColumn::make('registration_status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'approved' => 'success',
                    'pending' => 'warning',
                    'rejected' => 'danger',
                    'suspended' => 'gray',
                    default => 'gray',
                }),

            TextColumn::make('department.name')
                ->label('Department')
                ->placeholder('Unassigned'),

            IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),

            TextColumn::make('last_seen_at')
                ->label('Last Seen')
                ->dateTime()
                ->since()
                ->placeholder('Never')
                ->color(fn ($record) => $record->offline_alerted_at ? 'danger' : null),

            TextColumn::make('offline_alerted_at')
                ->label('Alert Sent')
                ->dateTime()
                ->since()
                ->placeholder('-')
                ->color('danger'),

            TextColumn::make('firmware_version')
                ->label('Firmware')
                ->placeholder('Unknown'),
        ])
            ->defaultSort('device_name')
            ->recordActions([
                EditAction::make(),
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
