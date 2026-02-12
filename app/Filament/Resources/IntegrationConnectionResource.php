<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntegrationConnectionResource\Pages\CreateIntegrationConnection;
use App\Filament\Resources\IntegrationConnectionResource\Pages\EditIntegrationConnection;
use App\Filament\Resources\IntegrationConnectionResource\Pages\ListIntegrationConnections;
use App\Filament\Resources\IntegrationConnectionResource\RelationManagers\IntegrationObjectsRelationManager;
use App\Models\IntegrationConnection;
use App\Services\Integrations\PaceApiClient;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;

class IntegrationConnectionResource extends Resource
{
    protected static ?string $model = IntegrationConnection::class;

    protected static ?string $navigationLabel = 'Integrations';

    protected static string|\UnitEnum|null $navigationGroup = 'Integrations';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Integration Connection')
                    ->tabs([
                        // =====================================================
                        // TAB 1: CONNECTION
                        // =====================================================
                        Tab::make('Connection')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Section::make('Basic Information')
                                    ->description('Identify this integration connection')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Connection Name')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('Friendly name: "Pace Production", "ADP Export"'),

                                        Select::make('integration_type')
                                            ->label('Integration Type')
                                            ->options(IntegrationConnection::getIntegrationTypes())
                                            ->required()
                                            ->live()
                                            ->afterStateHydrated(function ($state, $set, ?IntegrationConnection $record) {
                                                if ($record) {
                                                    $set('integration_type', $record->getIntegrationType());
                                                }
                                            })
                                            ->afterStateUpdated(function ($state, $set) {
                                                $parsed = IntegrationConnection::parseIntegrationType($state);
                                                $set('driver', $parsed['driver']);
                                                $set('integration_method', $parsed['method']);
                                            })
                                            ->helperText('Select the system and connection method'),

                                        // Hidden fields to store actual values
                                        Hidden::make('driver'),
                                        Hidden::make('integration_method'),

                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->helperText('Inactive connections will not sync or export'),
                                    ])
                                    ->columns(3),

                                Section::make('API Endpoint')
                                    ->description('Configure the API endpoint URL')
                                    ->schema([
                                        TextInput::make('base_url')
                                            ->label('API Base URL')
                                            ->required()
                                            ->url()
                                            ->placeholder('https://api.example.com')
                                            ->helperText('The base URL for API requests'),

                                        TextInput::make('api_version')
                                            ->label('API Version')
                                            ->placeholder('v1')
                                            ->nullable()
                                            ->helperText('Optional API version identifier'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Get $get): bool => str_ends_with($get('integration_type') ?? '', '_api')),

                                Section::make('Flat File Export Configuration')
                                    ->description('Configure where export files are saved')
                                    ->schema([
                                        Select::make('export_destination')
                                            ->label('Export Destination')
                                            ->options([
                                                'download' => 'Browser Download',
                                                'path' => 'Network/Server Path',
                                            ])
                                            ->default('download')
                                            ->required()
                                            ->live()
                                            ->helperText('Where exported files should be saved'),

                                        TextInput::make('export_path')
                                            ->label('Export Path')
                                            ->placeholder('/mnt/payroll/exports or \\\\server\\share\\payroll')
                                            ->required(fn (Get $get): bool => $get('export_destination') === 'path')
                                            ->helperText('Network path or server directory for export files')
                                            ->visible(fn (Get $get): bool => $get('export_destination') === 'path'),

                                        TextInput::make('export_filename_pattern')
                                            ->label('Filename Pattern')
                                            ->placeholder('payroll_{date}_{payperiod}.csv')
                                            ->helperText('Use {date}, {payperiod}, {timestamp} as placeholders')
                                            ->visible(fn (Get $get): bool => $get('export_destination') === 'path' && $get('integration_type') !== 'adp_file'),

                                        CheckboxList::make('export_formats')
                                            ->label('Export Format(s)')
                                            ->options([
                                                'csv' => 'CSV',
                                                'xlsx' => 'Excel (XLSX)',
                                                'txt' => 'Fixed-Width Text',
                                            ])
                                            ->columns(3)
                                            ->helperText('Select one or more export formats')
                                            ->visible(fn (Get $get): bool => $get('integration_type') !== 'adp_file'),

                                        Placeholder::make('flatfile_note')
                                            ->content('Flat file exports generate files on-demand when you process a pay period. Files are saved to the specified path or available for download.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Get $get): bool => in_array($get('integration_type'), ['adp_file', 'csv_export', 'excel_export'])),

                                Section::make('ADP Configuration')
                                    ->description('ADP-specific export settings')
                                    ->schema([
                                        TextInput::make('adp_company_code')
                                            ->label('ADP Company Code')
                                            ->required()
                                            ->maxLength(3)
                                            ->minLength(2)
                                            ->placeholder('ADP')
                                            ->helperText('Your 3-character ADP company code (found in ADP WFN → Setup → Company Setup). Used in filename: PRcccEPI.csv'),

                                        Select::make('adp_batch_format')
                                            ->label('Batch ID Format')
                                            ->options([
                                                'YYMMDD' => 'Date-based (YYMMDD) - Recommended',
                                                'sequential' => 'Sequential (000001, 000002, ...)',
                                            ])
                                            ->default('YYMMDD')
                                            ->helperText('How to generate the Batch ID for each export'),

                                        Placeholder::make('adp_format_info')
                                            ->content('ADP exports use a specific format: PRcccEPI.csv where ccc is your company code. The file contains one row per employee with hours pivoted into columns (Reg Hours, O/T Hours, and up to 3 Hours 3 Code/Amount pairs for Holiday, Vacation, Sick, etc.).')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Get $get): bool => $get('integration_type') === 'adp_file'),

                                Section::make('Connection Status')
                                    ->schema([
                                        Placeholder::make('last_connected_at_display')
                                            ->label('Last Successful Connection')
                                            ->content(fn (?IntegrationConnection $record): string => $record?->last_connected_at?->diffForHumans() ?? 'Never connected'),

                                        Placeholder::make('last_error_display')
                                            ->label('Last Error')
                                            ->content(fn (?IntegrationConnection $record): string => $record?->last_error_message ?? 'No errors')
                                            ->visible(fn (?IntegrationConnection $record): bool => $record?->last_error_at !== null),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Get $get, ?IntegrationConnection $record): bool => str_ends_with($get('integration_type') ?? '', '_api') && $record !== null),
                            ]),

                        // =====================================================
                        // TAB 2: AUTHENTICATION (API only)
                        // =====================================================
                        Tab::make('Authentication')
                            ->icon('heroicon-o-key')
                            ->visible(fn (Get $get): bool => str_ends_with($get('integration_type') ?? '', '_api'))
                            ->schema([
                                Section::make('Authentication Method')
                                    ->description('Configure how to authenticate with the API')
                                    ->schema([
                                        Select::make('auth_type')
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
                                Section::make('Basic Authentication')
                                    ->schema([
                                        TextInput::make('credentials.username')
                                            ->label('Username')
                                            ->required()
                                            ->helperText('API username'),

                                        TextInput::make('credentials.password')
                                            ->label('Password')
                                            ->password()
                                            ->revealable()
                                            ->required()
                                            ->helperText('API password (stored encrypted)'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Get $get): bool => $get('auth_type') === 'basic'),

                                // API Key fields
                                Section::make('API Key Authentication')
                                    ->schema([
                                        TextInput::make('credentials.api_key')
                                            ->label('API Key')
                                            ->password()
                                            ->revealable()
                                            ->required()
                                            ->helperText('Your API key (stored encrypted)'),

                                        Select::make('credentials.api_key_location')
                                            ->label('Key Location')
                                            ->options([
                                                'header' => 'Header (Authorization)',
                                                'query' => 'Query Parameter',
                                                'header_custom' => 'Custom Header',
                                            ])
                                            ->default('header')
                                            ->helperText('Where to send the API key'),

                                        TextInput::make('credentials.api_key_name')
                                            ->label('Header/Param Name')
                                            ->placeholder('X-API-Key')
                                            ->helperText('Custom header or param name')
                                            ->visible(fn (Get $get): bool => in_array($get('credentials.api_key_location'), ['query', 'header_custom'])),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Get $get): bool => $get('auth_type') === 'api_key'),

                                // Bearer Token fields
                                Section::make('Bearer Token')
                                    ->schema([
                                        Textarea::make('credentials.bearer_token')
                                            ->label('Bearer Token')
                                            ->required()
                                            ->rows(3)
                                            ->helperText('JWT or access token (stored encrypted)'),
                                    ])
                                    ->visible(fn (Get $get): bool => $get('auth_type') === 'bearer'),

                                // OAuth2 fields
                                Section::make('OAuth 2.0')
                                    ->schema([
                                        TextInput::make('credentials.client_id')
                                            ->label('Client ID')
                                            ->required(),

                                        TextInput::make('credentials.client_secret')
                                            ->label('Client Secret')
                                            ->password()
                                            ->revealable()
                                            ->required(),

                                        TextInput::make('credentials.token_url')
                                            ->label('Token URL')
                                            ->url()
                                            ->required()
                                            ->helperText('OAuth token endpoint'),

                                        TextInput::make('credentials.scope')
                                            ->label('Scopes')
                                            ->placeholder('read write')
                                            ->helperText('Space-separated list of scopes'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Get $get): bool => $get('auth_type') === 'oauth2'),
                            ]),

                        // =====================================================
                        // TAB 3: SETTINGS (API only)
                        // =====================================================
                        Tab::make('Settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->visible(fn (Get $get): bool => str_ends_with($get('integration_type') ?? '', '_api'))
                            ->schema([
                                Section::make('Request Settings')
                                    ->description('Configure API request behavior')
                                    ->schema([
                                        TextInput::make('timeout_seconds')
                                            ->label('Timeout (Seconds)')
                                            ->numeric()
                                            ->default(30)
                                            ->required()
                                            ->minValue(5)
                                            ->maxValue(300)
                                            ->helperText('Max seconds to wait for API response'),

                                        TextInput::make('retry_attempts')
                                            ->label('Retry Attempts')
                                            ->numeric()
                                            ->default(3)
                                            ->required()
                                            ->minValue(0)
                                            ->maxValue(10)
                                            ->helperText('Number of retries on failure'),

                                        TextInput::make('rate_limit_per_minute')
                                            ->label('Rate Limit (Requests/Minute)')
                                            ->numeric()
                                            ->nullable()
                                            ->helperText('Leave empty for no limit'),
                                    ])
                                    ->columns(3),

                                Section::make('Sync Schedule')
                                    ->description('Configure automatic sync polling')
                                    ->schema([
                                        TextInput::make('sync_interval_minutes')
                                            ->label('Sync Interval (Minutes)')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(1440)
                                            ->required()
                                            ->live()
                                            ->helperText('0 = push/webhook mode (no polling). Set a value to poll on a schedule.'),

                                        Placeholder::make('sync_mode_display')
                                            ->label('Current Mode')
                                            ->content(function (Get $get): string {
                                                $interval = (int) $get('sync_interval_minutes');
                                                if ($interval <= 0) {
                                                    return 'Push Mode — syncs are triggered manually or via webhook.';
                                                }
                                                if ($interval >= 60) {
                                                    $hours = floor($interval / 60);
                                                    $mins = $interval % 60;
                                                    $label = $hours.'h'.($mins > 0 ? " {$mins}m" : '');
                                                } else {
                                                    $label = $interval.'m';
                                                }

                                                return "Poll Mode — automatically syncs every {$label}.";
                                            }),

                                        Placeholder::make('last_synced_at_display')
                                            ->label('Last Synced')
                                            ->content(fn (?IntegrationConnection $record): string => $record?->last_synced_at?->diffForHumans() ?? 'Never synced')
                                            ->visible(fn (?IntegrationConnection $record): bool => $record !== null),
                                    ])
                                    ->columns(3),

                                Section::make('Push Webhook')
                                    ->description('Use this URL to trigger syncs from external systems or schedulers.')
                                    ->schema([
                                        Placeholder::make('webhook_url_display')
                                            ->label('Webhook URL (all objects)')
                                            ->content(fn (?IntegrationConnection $record): string => $record ? $record->getWebhookUrl() : 'Save the connection first to generate a webhook URL.'
                                            ),

                                        Placeholder::make('webhook_url_pattern')
                                            ->label('Per-Object URL Pattern')
                                            ->content(fn (?IntegrationConnection $record): string => $record ? $record->getWebhookUrl('{ObjectName}') : '—'
                                            ),

                                        Placeholder::make('webhook_usage')
                                            ->content('Send a POST request to the webhook URL to trigger a sync. Use the per-object URL to sync a specific object type (e.g., /Employee). No request body is required.')
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn (Get $get, ?IntegrationConnection $record): bool => (int) $get('sync_interval_minutes') <= 0 && $record !== null
                                    )
                                    ->columns(2),

                                Section::make('Pace-Specific Settings')
                                    ->description('Settings specific to Pace/ePace integration')
                                    ->schema([
                                        Placeholder::make('pace_info')
                                            ->content('Pace uses loadValueObjects for efficient data retrieval. Configure query templates to define what data to fetch.')
                                            ->columnSpanFull(),

                                        TextInput::make('credentials.pace_company')
                                            ->label('Pace Company Code')
                                            ->helperText('Company code if using multi-company Pace'),
                                    ])
                                    ->visible(fn (Get $get): bool => $get('integration_type') === 'pace_api')
                                    ->collapsed(),
                            ]),

                        // =====================================================
                        // TAB 4: PAYROLL EXPORT
                        // =====================================================
                        Tab::make('Payroll Export')
                            ->icon('heroicon-o-document-arrow-down')
                            ->schema([
                                Section::make('Payroll Provider Configuration')
                                    ->description('Configure this integration as a payroll export destination')
                                    ->schema([
                                        Toggle::make('is_payroll_provider')
                                            ->label('Enable as Payroll Provider')
                                            ->default(fn (Get $get): bool => in_array($get('integration_type'), ['adp_file', 'csv_export', 'excel_export']))
                                            ->helperText('Mark this connection as a destination for payroll exports')
                                            ->live()
                                            ->columnSpanFull(),

                                        Placeholder::make('flatfile_note')
                                            ->content('This is a flat file integration. Export format and destination are configured in the Connection tab.')
                                            ->visible(fn (Get $get): bool => in_array($get('integration_type'), ['adp_file', 'csv_export', 'excel_export']))
                                            ->columnSpanFull(),
                                    ]),

                                // API method: show export formats and destination here
                                Section::make('Export Formats')
                                    ->description('Select which export formats to enable for this provider')
                                    ->schema([
                                        CheckboxList::make('export_formats')
                                            ->label('Enabled Formats')
                                            ->options([
                                                'csv' => 'CSV (Comma-Separated Values)',
                                                'xlsx' => 'Excel (XLSX)',
                                                'json' => 'JSON',
                                                'xml' => 'XML',
                                            ])
                                            ->columns(2)
                                            ->helperText('Select one or more export formats'),
                                    ])
                                    ->visible(fn (Get $get): bool => $get('is_payroll_provider') === true &&
                                        str_ends_with($get('integration_type') ?? '', '_api')
                                    ),

                                Section::make('Export Destination')
                                    ->description('Configure where exported files should be saved')
                                    ->schema([
                                        Select::make('export_destination')
                                            ->label('Destination')
                                            ->options([
                                                'download' => 'Download (browser download)',
                                                'path' => 'File Path (save to server)',
                                            ])
                                            ->default('download')
                                            ->live()
                                            ->helperText('Where to save generated export files'),

                                        TextInput::make('export_path')
                                            ->label('Export Path')
                                            ->placeholder('/var/exports/payroll')
                                            ->helperText('Server path where export files will be saved')
                                            ->visible(fn (Get $get): bool => $get('export_destination') === 'path'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Get $get): bool => $get('is_payroll_provider') === true &&
                                        str_ends_with($get('integration_type') ?? '', '_api')
                                    ),

                                Section::make('Assigned Employees')
                                    ->description('Employees assigned to this payroll provider')
                                    ->schema([
                                        Placeholder::make('employee_count')
                                            ->label('Employees')
                                            ->content(fn (?IntegrationConnection $record): string => $record ? ($record->employees()->count().' employees assigned') : 'Save to view employee count'
                                            ),
                                    ])
                                    ->visible(fn (Get $get, ?IntegrationConnection $record): bool => $get('is_payroll_provider') === true && $record !== null
                                    ),
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
                IconColumn::make('is_active')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('name')
                    ->label('Connection Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('integration_type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(fn (IntegrationConnection $record): string => $record->getIntegrationType())
                    ->color(fn (string $state): string => match ($state) {
                        'pace_api' => 'success',
                        'adp_api', 'adp_file' => 'info',
                        'quickbooks_api' => 'warning',
                        'csv_export', 'excel_export' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => IntegrationConnection::getIntegrationTypes()[$state] ?? $state),

                TextColumn::make('base_url')
                    ->label('Endpoint/Path')
                    ->limit(40)
                    ->getStateUsing(fn (IntegrationConnection $record): string => $record->isFlatFileMethod()
                            ? ($record->export_path ?? 'Download')
                            : ($record->base_url ?? '-')
                    )
                    ->tooltip(fn (IntegrationConnection $record): string => $record->isFlatFileMethod()
                            ? ($record->export_path ?? 'Browser download')
                            : ($record->base_url ?? '')
                    ),

                TextColumn::make('auth_type')
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

                TextColumn::make('last_connected_at')
                    ->label('Last Connected')
                    ->dateTime()
                    ->since()
                    ->placeholder('Never')
                    ->color(fn ($record) => $record->last_error_at > $record->last_connected_at ? 'danger' : null),

                IconColumn::make('has_error')
                    ->label('Error')
                    ->getStateUsing(fn ($record): bool => $record->last_error_at !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger'),

                TextColumn::make('sync_interval_minutes')
                    ->label('Sync')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "Every {$state}m" : 'Push')
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),

                TextColumn::make('last_synced_at')
                    ->label('Last Synced')
                    ->dateTime()
                    ->since()
                    ->placeholder('Never'),

                TextColumn::make('objects_count')
                    ->label('Objects')
                    ->counts('objects')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                SelectFilter::make('integration_type')
                    ->label('Type')
                    ->options(IntegrationConnection::getIntegrationTypes())
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) {
                            return;
                        }
                        $parsed = IntegrationConnection::parseIntegrationType($data['value']);
                        $query->where('driver', $parsed['driver'])
                            ->where('integration_method', $parsed['method']);
                    }),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('is_payroll_provider')
                    ->label('Payroll Provider'),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(function (IntegrationConnection $record) {
                        if ($record->driver === 'pace') {
                            $client = new PaceApiClient($record);
                            $result = $client->testConnection();
                        } else {
                            try {
                                $response = Http::timeout($record->timeout_seconds)
                                    ->get($record->base_url);
                                $record->markConnected();
                                $result = ['success' => true, 'message' => 'HTTP '.$response->status()];
                            } catch (Exception $e) {
                                $record->markError($e->getMessage());
                                $result = ['success' => false, 'message' => $e->getMessage()];
                            }
                        }

                        if ($result['success']) {
                            $body = $result['message'];
                            if (! empty($result['version'])) {
                                $body = 'Pace Version: '.$result['version'];
                            }
                            Notification::make()
                                ->title('Connection Successful')
                                ->body($body)
                                ->success()
                                ->duration(10000)
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Connection Failed')
                                ->body($result['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            IntegrationObjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrationConnections::route('/'),
            'create' => CreateIntegrationConnection::route('/create'),
            'edit' => EditIntegrationConnection::route('/{record}/edit'),
        ];
    }
}
