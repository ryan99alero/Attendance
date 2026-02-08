<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntegrationConnectionResource\Pages;
use App\Filament\Resources\IntegrationConnectionResource\RelationManagers;
use App\Models\IntegrationConnection;
use App\Services\Integrations\PaceApiClient;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Tables;
use Filament\Tables\Table;

class IntegrationConnectionResource extends Resource
{
    protected static ?string $model = IntegrationConnection::class;

    protected static ?string $navigationLabel = 'Integrations';
    protected static ?string $navigationGroup = 'Integrations';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?int $navigationSort = 10;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Tabs::make('Integration Connection')
                    ->tabs([
                        // =====================================================
                        // TAB 1: CONNECTION
                        // =====================================================
                        Tabs\Tab::make('Connection')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Forms\Components\Section::make('Basic Information')
                                    ->description('Identify this integration connection')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Connection Name')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('Human-friendly name: "Pace Production", "ADP Payroll"'),

                                        Forms\Components\Select::make('driver')
                                            ->label('Integration Type')
                                            ->options([
                                                'pace' => 'Pace / ePace ERP',
                                                'adp' => 'ADP Payroll',
                                                'quickbooks' => 'QuickBooks',
                                                'generic_rest' => 'Generic REST API',
                                            ])
                                            ->required()
                                            ->live()
                                            ->helperText('Select the type of system to connect to'),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->helperText('Inactive connections will not sync'),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('API Endpoint')
                                    ->description('Configure the API endpoint URL')
                                    ->schema([
                                        Forms\Components\TextInput::make('base_url')
                                            ->label('Base URL')
                                            ->required()
                                            ->url()
                                            ->placeholder('https://api.example.com')
                                            ->helperText('The base URL for API requests'),

                                        Forms\Components\TextInput::make('api_version')
                                            ->label('API Version')
                                            ->placeholder('v1')
                                            ->nullable()
                                            ->helperText('Optional API version identifier'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Connection Status')
                                    ->schema([
                                        Forms\Components\Placeholder::make('last_connected_at_display')
                                            ->label('Last Successful Connection')
                                            ->content(fn (?IntegrationConnection $record): string =>
                                                $record?->last_connected_at?->diffForHumans() ?? 'Never connected'),

                                        Forms\Components\Placeholder::make('last_error_display')
                                            ->label('Last Error')
                                            ->content(fn (?IntegrationConnection $record): string =>
                                                $record?->last_error_message ?? 'No errors')
                                            ->visible(fn (?IntegrationConnection $record): bool =>
                                                $record?->last_error_at !== null),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (?IntegrationConnection $record): bool => $record !== null),
                            ]),

                        // =====================================================
                        // TAB 2: AUTHENTICATION
                        // =====================================================
                        Tabs\Tab::make('Authentication')
                            ->icon('heroicon-o-key')
                            ->schema([
                                Forms\Components\Section::make('Authentication Method')
                                    ->description('Configure how to authenticate with the API')
                                    ->schema([
                                        Forms\Components\Select::make('auth_type')
                                            ->label('Authentication Type')
                                            ->options([
                                                'basic' => 'Basic Authentication (Username/Password)',
                                                'api_key' => 'API Key',
                                                'oauth2' => 'OAuth 2.0',
                                                'bearer' => 'Bearer Token',
                                            ])
                                            ->default('basic')
                                            ->required()
                                            ->live()
                                            ->helperText('Method used to authenticate API requests'),
                                    ]),

                                // Basic Auth fields
                                Forms\Components\Section::make('Basic Authentication')
                                    ->schema([
                                        Forms\Components\TextInput::make('credentials.username')
                                            ->label('Username')
                                            ->required()
                                            ->helperText('API username'),

                                        Forms\Components\TextInput::make('credentials.password')
                                            ->label('Password')
                                            ->password()
                                            ->revealable()
                                            ->required()
                                            ->helperText('API password (stored encrypted)'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Forms\Get $get): bool => $get('auth_type') === 'basic'),

                                // API Key fields
                                Forms\Components\Section::make('API Key Authentication')
                                    ->schema([
                                        Forms\Components\TextInput::make('credentials.api_key')
                                            ->label('API Key')
                                            ->password()
                                            ->revealable()
                                            ->required()
                                            ->helperText('Your API key (stored encrypted)'),

                                        Forms\Components\Select::make('credentials.api_key_location')
                                            ->label('Key Location')
                                            ->options([
                                                'header' => 'Header (Authorization)',
                                                'query' => 'Query Parameter',
                                                'header_custom' => 'Custom Header',
                                            ])
                                            ->default('header')
                                            ->helperText('Where to send the API key'),

                                        Forms\Components\TextInput::make('credentials.api_key_name')
                                            ->label('Header/Param Name')
                                            ->placeholder('X-API-Key')
                                            ->helperText('Custom header or param name')
                                            ->visible(fn (Forms\Get $get): bool =>
                                                in_array($get('credentials.api_key_location'), ['query', 'header_custom'])),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Forms\Get $get): bool => $get('auth_type') === 'api_key'),

                                // Bearer Token fields
                                Forms\Components\Section::make('Bearer Token')
                                    ->schema([
                                        Forms\Components\Textarea::make('credentials.bearer_token')
                                            ->label('Bearer Token')
                                            ->required()
                                            ->rows(3)
                                            ->helperText('JWT or access token (stored encrypted)'),
                                    ])
                                    ->visible(fn (Forms\Get $get): bool => $get('auth_type') === 'bearer'),

                                // OAuth2 fields
                                Forms\Components\Section::make('OAuth 2.0')
                                    ->schema([
                                        Forms\Components\TextInput::make('credentials.client_id')
                                            ->label('Client ID')
                                            ->required(),

                                        Forms\Components\TextInput::make('credentials.client_secret')
                                            ->label('Client Secret')
                                            ->password()
                                            ->revealable()
                                            ->required(),

                                        Forms\Components\TextInput::make('credentials.token_url')
                                            ->label('Token URL')
                                            ->url()
                                            ->required()
                                            ->helperText('OAuth token endpoint'),

                                        Forms\Components\TextInput::make('credentials.scope')
                                            ->label('Scopes')
                                            ->placeholder('read write')
                                            ->helperText('Space-separated list of scopes'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Forms\Get $get): bool => $get('auth_type') === 'oauth2'),
                            ]),

                        // =====================================================
                        // TAB 3: SETTINGS
                        // =====================================================
                        Tabs\Tab::make('Settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Section::make('Request Settings')
                                    ->description('Configure API request behavior')
                                    ->schema([
                                        Forms\Components\TextInput::make('timeout_seconds')
                                            ->label('Timeout (Seconds)')
                                            ->numeric()
                                            ->default(30)
                                            ->required()
                                            ->minValue(5)
                                            ->maxValue(300)
                                            ->helperText('Max seconds to wait for API response'),

                                        Forms\Components\TextInput::make('retry_attempts')
                                            ->label('Retry Attempts')
                                            ->numeric()
                                            ->default(3)
                                            ->required()
                                            ->minValue(0)
                                            ->maxValue(10)
                                            ->helperText('Number of retries on failure'),

                                        Forms\Components\TextInput::make('rate_limit_per_minute')
                                            ->label('Rate Limit (Requests/Minute)')
                                            ->numeric()
                                            ->nullable()
                                            ->helperText('Leave empty for no limit'),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('Sync Schedule')
                                    ->description('Configure automatic sync polling')
                                    ->schema([
                                        Forms\Components\TextInput::make('sync_interval_minutes')
                                            ->label('Sync Interval (Minutes)')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(1440)
                                            ->required()
                                            ->live()
                                            ->helperText('0 = push/webhook mode (no polling). Set a value to poll on a schedule.'),

                                        Forms\Components\Placeholder::make('sync_mode_display')
                                            ->label('Current Mode')
                                            ->content(function (Forms\Get $get): string {
                                                $interval = (int) $get('sync_interval_minutes');
                                                if ($interval <= 0) {
                                                    return 'Push Mode — syncs are triggered manually or via webhook.';
                                                }
                                                if ($interval >= 60) {
                                                    $hours = floor($interval / 60);
                                                    $mins = $interval % 60;
                                                    $label = $hours . 'h' . ($mins > 0 ? " {$mins}m" : '');
                                                } else {
                                                    $label = $interval . 'm';
                                                }
                                                return "Poll Mode — automatically syncs every {$label}.";
                                            }),

                                        Forms\Components\Placeholder::make('last_synced_at_display')
                                            ->label('Last Synced')
                                            ->content(fn (?IntegrationConnection $record): string =>
                                                $record?->last_synced_at?->diffForHumans() ?? 'Never synced')
                                            ->visible(fn (?IntegrationConnection $record): bool => $record !== null),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('Push Webhook')
                                    ->description('Use this URL to trigger syncs from external systems or schedulers.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('webhook_url_display')
                                            ->label('Webhook URL (all objects)')
                                            ->content(fn (?IntegrationConnection $record): string =>
                                                $record ? $record->getWebhookUrl() : 'Save the connection first to generate a webhook URL.'
                                            ),

                                        Forms\Components\Placeholder::make('webhook_url_pattern')
                                            ->label('Per-Object URL Pattern')
                                            ->content(fn (?IntegrationConnection $record): string =>
                                                $record ? $record->getWebhookUrl('{ObjectName}') : '—'
                                            ),

                                        Forms\Components\Placeholder::make('webhook_usage')
                                            ->content('Send a POST request to the webhook URL to trigger a sync. Use the per-object URL to sync a specific object type (e.g., /Employee). No request body is required.')
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn (Forms\Get $get, ?IntegrationConnection $record): bool =>
                                        (int) $get('sync_interval_minutes') <= 0 && $record !== null
                                    )
                                    ->columns(2),

                                Forms\Components\Section::make('Pace-Specific Settings')
                                    ->description('Settings specific to Pace/ePace integration')
                                    ->schema([
                                        Forms\Components\Placeholder::make('pace_info')
                                            ->content('Pace uses loadValueObjects for efficient data retrieval. Configure query templates to define what data to fetch.')
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('credentials.pace_company')
                                            ->label('Pace Company Code')
                                            ->helperText('Company code if using multi-company Pace'),
                                    ])
                                    ->visible(fn (Forms\Get $get): bool => $get('driver') === 'pace')
                                    ->collapsed(),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Connection Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('driver')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pace' => 'success',
                        'adp' => 'info',
                        'quickbooks' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pace' => 'Pace ERP',
                        'adp' => 'ADP',
                        'quickbooks' => 'QuickBooks',
                        'generic_rest' => 'REST API',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('base_url')
                    ->label('Endpoint')
                    ->limit(40)
                    ->tooltip(fn ($record): string => $record->base_url),

                Tables\Columns\TextColumn::make('auth_type')
                    ->label('Auth')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'basic' => 'Basic',
                        'api_key' => 'API Key',
                        'oauth2' => 'OAuth',
                        'bearer' => 'Bearer',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('last_connected_at')
                    ->label('Last Connected')
                    ->dateTime()
                    ->since()
                    ->placeholder('Never')
                    ->color(fn ($record) => $record->last_error_at > $record->last_connected_at ? 'danger' : null),

                Tables\Columns\IconColumn::make('has_error')
                    ->label('Error')
                    ->getStateUsing(fn ($record): bool => $record->last_error_at !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger'),

                Tables\Columns\TextColumn::make('sync_interval_minutes')
                    ->label('Sync')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "Every {$state}m" : 'Push')
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last Synced')
                    ->dateTime()
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('objects_count')
                    ->label('Objects')
                    ->counts('objects')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('driver')
                    ->options([
                        'pace' => 'Pace ERP',
                        'adp' => 'ADP',
                        'quickbooks' => 'QuickBooks',
                        'generic_rest' => 'REST API',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(function (IntegrationConnection $record) {
                        if ($record->driver === 'pace') {
                            $client = new PaceApiClient($record);
                            $result = $client->testConnection();
                        } else {
                            try {
                                $response = \Illuminate\Support\Facades\Http::timeout($record->timeout_seconds)
                                    ->get($record->base_url);
                                $record->markConnected();
                                $result = ['success' => true, 'message' => 'HTTP ' . $response->status()];
                            } catch (\Exception $e) {
                                $record->markError($e->getMessage());
                                $result = ['success' => false, 'message' => $e->getMessage()];
                            }
                        }

                        if ($result['success']) {
                            $body = $result['message'];
                            if (!empty($result['version'])) {
                                $body = 'Pace Version: ' . $result['version'];
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('Connection Successful')
                                ->body($body)
                                ->success()
                                ->duration(10000)
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Connection Failed')
                                ->body($result['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\IntegrationObjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntegrationConnections::route('/'),
            'create' => Pages\CreateIntegrationConnection::route('/create'),
            'edit' => Pages\EditIntegrationConnection::route('/{record}/edit'),
        ];
    }
}
