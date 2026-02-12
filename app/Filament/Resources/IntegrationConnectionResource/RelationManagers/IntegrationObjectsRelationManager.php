<?php

namespace App\Filament\Resources\IntegrationConnectionResource\RelationManagers;

use App\Models\IntegrationFieldMapping;
use App\Services\Integrations\PaceApiClient;
use App\Services\ModelDiscoveryService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntegrationObjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'objects';

    protected static ?string $title = 'Objects';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-cube';

    public function form(Schema $schema): Schema
    {
        $discoveryService = new ModelDiscoveryService;

        return $schema
            ->columns(1)
            ->components([
                // Section 1: Object Definition
                Section::make(function () {
                    $connection = $this->getOwnerRecord();

                    return $connection->isFlatFileMethod() ? 'Export Definition' : 'Object Definition';
                })
                    ->description(function () {
                        $connection = $this->getOwnerRecord();

                        return $connection->isFlatFileMethod()
                            ? 'Define what data to export and from which table'
                            : 'Define the API object and local mapping';
                    })
                    ->columnSpanFull()
                    ->schema([
                        Select::make('object_name')
                            ->label(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isFlatFileMethod() ? 'Export Type' : 'Object Name';
                            })
                            ->options(function () {
                                /** @var \App\Models\IntegrationConnection $connection */
                                $connection = $this->getOwnerRecord();

                                // Only use PaceApiClient for Pace API integrations
                                if ($connection->driver === 'pace' && $connection->isApiMethod()) {
                                    return (new PaceApiClient($connection))->getCommonObjectTypes();
                                }

                                // For flat file exports and other integrations, provide common object types
                                return [
                                    'Employee' => 'Employee Data',
                                    'TimeEntry' => 'Time Entries',
                                    'PayPeriodSummary' => 'Pay Period Summary',
                                    'Department' => 'Departments',
                                    'custom' => '-- Custom --',
                                ];
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $connection = $this->getOwnerRecord();
                                if (! $connection->isFlatFileMethod()) {
                                    return;
                                }

                                // Auto-set the model based on export type selection
                                $modelMap = [
                                    'Employee' => \App\Models\Employee::class,
                                    'TimeEntry' => \App\Models\TimeEntry::class,
                                    'PayPeriodSummary' => \App\Models\PayPeriodEmployeeSummary::class,
                                    'Department' => \App\Models\Department::class,
                                ];

                                if (isset($modelMap[$state])) {
                                    $model = $modelMap[$state];
                                    $set('local_model', $model);
                                    if (class_exists($model)) {
                                        $set('local_table', (new $model)->getTable());
                                    }
                                } else {
                                    $set('local_model', null);
                                    $set('local_table', null);
                                }
                            })
                            ->helperText(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isFlatFileMethod()
                                    ? 'What type of data to export'
                                    : 'Select an API object type';
                            }),

                        TextInput::make('custom_object_name')
                            ->label('Custom Name')
                            ->visible(fn (Get $get) => $get('object_name') === 'custom')
                            ->required(fn (Get $get) => $get('object_name') === 'custom')
                            ->helperText('Enter a custom name'),

                        TextInput::make('display_name')
                            ->label('Display Name')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Friendly name shown in the UI'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->visible(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isApiMethod();
                            }),

                        // API-only fields
                        TextInput::make('primary_key_field')
                            ->label('API Primary Key XPath')
                            ->default('@id')
                            ->required()
                            ->visible(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isApiMethod();
                            })
                            ->helperText('XPath to the source API\'s primary key (e.g. @id)'),

                        Select::make('primary_key_type')
                            ->label('API Primary Key Type')
                            ->options([
                                'String' => 'String',
                                'Integer' => 'Integer',
                            ])
                            ->default('String')
                            ->required()
                            ->visible(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isApiMethod();
                            })
                            ->helperText('Data type as returned by the API'),

                        Select::make('local_model')
                            ->label(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isFlatFileMethod() ? 'Data Source (Model)' : 'Local Model';
                            })
                            ->options(fn () => $discoveryService->getModelOptionsForSelect())
                            ->searchable()
                            ->required(fn (Get $get) => $get('object_name') === 'custom')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state && class_exists($state)) {
                                    $model = new $state;
                                    $set('local_table', $model->getTable());
                                }
                            })
                            ->visible(function (Get $get) {
                                $connection = $this->getOwnerRecord();

                                // Show for API integrations OR custom flat file exports
                                return $connection->isApiMethod() || $get('object_name') === 'custom';
                            })
                            ->helperText(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isFlatFileMethod()
                                    ? 'Select which data to export'
                                    : 'Local model to sync data to';
                            }),

                        TextInput::make('local_table')
                            ->label(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isFlatFileMethod() ? 'Source Table' : 'Local Table';
                            })
                            ->disabled()
                            ->dehydrated()
                            ->visible(function (Get $get) {
                                $connection = $this->getOwnerRecord();

                                // Show for API integrations OR custom flat file exports
                                return $connection->isApiMethod() || $get('object_name') === 'custom';
                            }),

                        TextInput::make('default_filter')
                            ->label('Default Filter')
                            ->placeholder("@status = 'A'")
                            ->helperText('Default XPath filter expression for syncs')
                            ->columnSpanFull()
                            ->visible(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isApiMethod();
                            }),
                    ])
                    ->columns(2),

                // Section 2: Sync/Export Settings
                Section::make(function () {
                    $connection = $this->getOwnerRecord();

                    return $connection->isFlatFileMethod() ? 'Export Settings' : 'Sync Settings';
                })
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('sync_enabled')
                            ->label(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isFlatFileMethod() ? 'Export Enabled' : 'Sync Enabled';
                            })
                            ->default(true),

                        Select::make('sync_direction')
                            ->label('Direction')
                            ->options(function () {
                                $connection = $this->getOwnerRecord();
                                if ($connection->isFlatFileMethod()) {
                                    return [
                                        'push' => 'Export (Local → File)',
                                    ];
                                }

                                return [
                                    'pull' => 'Pull (API → Local)',
                                    'push' => 'Push (Local → API)',
                                    'bidirectional' => 'Bidirectional',
                                ];
                            })
                            ->default(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isFlatFileMethod() ? 'push' : 'pull';
                            })
                            ->required(),

                        Select::make('api_method')
                            ->label('API Method')
                            ->options([
                                'loadValueObjects' => 'Load Value Objects (bulk read)',
                                'findObjects' => 'Find Objects (search)',
                                'createObject' => 'Create Object',
                                'updateObject' => 'Update Object',
                            ])
                            ->default('loadValueObjects')
                            ->visible(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isApiMethod() && $connection->driver === 'pace';
                            })
                            ->helperText('Pace API method to use for this object'),

                        Select::make('sync_frequency')
                            ->label(function () {
                                $connection = $this->getOwnerRecord();

                                return $connection->isFlatFileMethod() ? 'Export Frequency' : 'Sync Frequency';
                            })
                            ->options([
                                'manual' => 'Manual',
                                'hourly' => 'Hourly',
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                            ])
                            ->default('manual'),
                    ])
                    ->columns(4),

                // Section 3: Field Mappings (Repeater)
                Section::make(function () {
                    $connection = $this->getOwnerRecord();

                    return $connection->isFlatFileMethod() ? 'Export Field Mappings' : 'Field Mappings';
                })
                    ->description(function () {
                        $connection = $this->getOwnerRecord();

                        return $connection->isFlatFileMethod()
                            ? 'Define which local fields to export and their column names in the output file'
                            : 'Map external API fields to local database fields';
                    })
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('fieldMappings')
                            ->relationship()
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                // Auto-derive external_field from local_field (or xpath basename)
                                if (empty($data['external_field'])) {
                                    $data['external_field'] = $data['local_field']
                                        ?: ltrim(basename($data['external_xpath'] ?? ''), '@');
                                }
                                // Build transform_options for fk_lookup
                                if (($data['transform'] ?? null) === 'fk_lookup') {
                                    $data['transform_options'] = [
                                        'model' => $data['lookup_model'] ?? null,
                                        'match_column' => $data['lookup_match_column'] ?? null,
                                        'return_column' => $data['lookup_return_column'] ?? 'id',
                                    ];
                                }
                                // Build transform_options for value_map
                                if (($data['transform'] ?? null) === 'value_map') {
                                    $map = $data['value_map_entries'] ?? [];
                                    // Convert string booleans to actual booleans
                                    foreach ($map as $key => $value) {
                                        if ($value === 'true') {
                                            $map[$key] = true;
                                        } elseif ($value === 'false') {
                                            $map[$key] = false;
                                        }
                                    }
                                    $data['transform_options'] = [
                                        'map' => $map,
                                        'default' => $data['value_map_default'] ?? null,
                                    ];
                                }
                                // Remove temporary fields
                                unset($data['lookup_model'], $data['lookup_match_column'], $data['lookup_return_column']);
                                unset($data['value_map_entries'], $data['value_map_default']);

                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                // Auto-derive external_field from local_field (or xpath basename)
                                if (empty($data['external_field'])) {
                                    $data['external_field'] = $data['local_field']
                                        ?: ltrim(basename($data['external_xpath'] ?? ''), '@');
                                }
                                // Build transform_options for fk_lookup
                                if (($data['transform'] ?? null) === 'fk_lookup') {
                                    $data['transform_options'] = [
                                        'model' => $data['lookup_model'] ?? null,
                                        'match_column' => $data['lookup_match_column'] ?? null,
                                        'return_column' => $data['lookup_return_column'] ?? 'id',
                                    ];
                                    // Build transform_options for value_map
                                } elseif (($data['transform'] ?? null) === 'value_map') {
                                    $map = $data['value_map_entries'] ?? [];
                                    // Convert string booleans to actual booleans
                                    foreach ($map as $key => $value) {
                                        if ($value === 'true') {
                                            $map[$key] = true;
                                        } elseif ($value === 'false') {
                                            $map[$key] = false;
                                        }
                                    }
                                    $data['transform_options'] = [
                                        'map' => $map,
                                        'default' => $data['value_map_default'] ?? null,
                                    ];
                                } else {
                                    // Clear transform_options if not fk_lookup or value_map
                                    $data['transform_options'] = null;
                                }
                                // Remove temporary fields
                                unset($data['lookup_model'], $data['lookup_match_column'], $data['lookup_return_column']);
                                unset($data['value_map_entries'], $data['value_map_default']);

                                return $data;
                            })
                            ->schema([
                                TextInput::make('external_xpath')
                                    ->label(function () {
                                        $connection = $this->getOwnerRecord();

                                        return $connection->isFlatFileMethod() ? 'Export Column Name' : 'API Field (XPath)';
                                    })
                                    ->placeholder(function () {
                                        $connection = $this->getOwnerRecord();

                                        return $connection->isFlatFileMethod() ? 'EmployeeID' : '@firstName';
                                    })
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText(function () {
                                        $connection = $this->getOwnerRecord();

                                        return $connection->isFlatFileMethod()
                                            ? 'Column header name in the exported file'
                                            : 'API field path (e.g. @firstName, /country/@isoCountry)';
                                    }),

                                Hidden::make('external_field'),

                                Select::make('external_type')
                                    ->label(function () {
                                        $connection = $this->getOwnerRecord();

                                        return $connection->isFlatFileMethod() ? 'Output Type' : 'External Type';
                                    })
                                    ->options([
                                        'String' => 'String',
                                        'Integer' => 'Integer',
                                        'Float' => 'Float',
                                        'Date' => 'Date',
                                        'Boolean' => 'Boolean',
                                        'Currency' => 'Currency',
                                    ])
                                    ->default('String')
                                    ->required(),

                                Select::make('local_table')
                                    ->label('Local Table')
                                    ->options(function (Get $get, $record) use ($discoveryService) {
                                        $localModel = $get('../../local_model');

                                        // Fallback: read from the DB record when $get can't traverse the repeater boundary
                                        if (empty($localModel) && $record instanceof IntegrationFieldMapping) {
                                            $localModel = $record->object?->local_model;
                                        }

                                        if (! $localModel || ! class_exists($localModel)) {
                                            return [];
                                        }

                                        return $discoveryService->getTableOptionsForModel($localModel);
                                    })
                                    ->reactive()
                                    ->placeholder('Primary table')
                                    ->helperText('Leave empty for primary table'),

                                Select::make('local_field')
                                    ->label('Local Field')
                                    ->options(function (Get $get, $record) use ($discoveryService) {
                                        $selectedTable = $get('local_table');
                                        $localModel = $get('../../local_model');

                                        // Fallback: read from the DB record when $get can't traverse the repeater boundary
                                        if (empty($localModel) && $record instanceof IntegrationFieldMapping) {
                                            $localModel = $record->object?->local_model;
                                        }

                                        if ($selectedTable) {
                                            return $discoveryService->getTableColumns($selectedTable);
                                        }

                                        if ($localModel && class_exists($localModel)) {
                                            $primaryTable = (new $localModel)->getTable();

                                            return $discoveryService->getTableColumns($primaryTable);
                                        }

                                        return [];
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->dehydrateStateUsing(fn ($state) => $state ?? '')
                                    ->helperText('Leave empty for fetch-only fields'),

                                Select::make('local_type')
                                    ->label('Local Type')
                                    ->options([
                                        'string' => 'String',
                                        'integer' => 'Integer',
                                        'float' => 'Float',
                                        'datetime' => 'DateTime',
                                        'date' => 'Date',
                                        'boolean' => 'Boolean',
                                    ])
                                    ->default('string')
                                    ->required(),

                                Select::make('transform')
                                    ->label('Transform')
                                    ->options([
                                        'date_ms_to_carbon' => 'Date (ms) → Carbon',
                                        'date_iso_to_carbon' => 'Date (ISO) → Carbon',
                                        'string_to_int' => 'String → Integer',
                                        'string_to_float' => 'String → Float',
                                        'string_to_bool' => 'String → Boolean',
                                        'cents_to_dollars' => 'Cents → Dollars',
                                        'fk_lookup' => 'FK Lookup',
                                        'value_map' => 'Value Map',
                                        'trim' => 'Trim',
                                        'uppercase' => 'Uppercase',
                                        'lowercase' => 'Lowercase',
                                        'json_decode' => 'JSON Decode',
                                    ])
                                    ->placeholder('None')
                                    ->live()
                                    ->dehydrateStateUsing(fn ($state) => $state ?: null)
                                    ->nullable(),

                                // FK Lookup configuration fields
                                Select::make('lookup_model')
                                    ->label('Lookup Model')
                                    ->options(fn () => $discoveryService->getModelOptionsForSelect())
                                    ->searchable()
                                    ->live()
                                    ->visible(fn (Get $get) => $get('transform') === 'fk_lookup')
                                    ->required(fn (Get $get) => $get('transform') === 'fk_lookup')
                                    ->afterStateHydrated(function ($state, $record, Set $set) {
                                        // Pre-populate from transform_options when editing
                                        if (! $state && $record instanceof IntegrationFieldMapping) {
                                            $options = $record->transform_options ?? [];
                                            $set('lookup_model', $options['model'] ?? null);
                                        }
                                    })
                                    ->helperText('Model to look up the foreign key in'),

                                Select::make('lookup_match_column')
                                    ->label('Match Column')
                                    ->options(function (Get $get) use ($discoveryService) {
                                        $model = $get('lookup_model');
                                        if ($model && class_exists($model)) {
                                            $table = (new $model)->getTable();

                                            return $discoveryService->getTableColumns($table);
                                        }

                                        return [];
                                    })
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('transform') === 'fk_lookup')
                                    ->required(fn (Get $get) => $get('transform') === 'fk_lookup')
                                    ->afterStateHydrated(function ($state, $record, Set $set) {
                                        // Pre-populate from transform_options when editing
                                        if (! $state && $record instanceof IntegrationFieldMapping) {
                                            $options = $record->transform_options ?? [];
                                            $set('lookup_match_column', $options['match_column'] ?? null);
                                        }
                                    })
                                    ->helperText('Column to match API value against'),

                                Select::make('lookup_return_column')
                                    ->label('Return Column')
                                    ->options(function (Get $get) use ($discoveryService) {
                                        $model = $get('lookup_model');
                                        if ($model && class_exists($model)) {
                                            $table = (new $model)->getTable();

                                            return $discoveryService->getTableColumns($table);
                                        }

                                        return [];
                                    })
                                    ->searchable()
                                    ->default('id')
                                    ->visible(fn (Get $get) => $get('transform') === 'fk_lookup')
                                    ->required(fn (Get $get) => $get('transform') === 'fk_lookup')
                                    ->afterStateHydrated(function ($state, $record, Set $set) {
                                        // Pre-populate from transform_options when editing
                                        if (! $state && $record instanceof IntegrationFieldMapping) {
                                            $options = $record->transform_options ?? [];
                                            $set('lookup_return_column', $options['return_column'] ?? 'id');
                                        }
                                    })
                                    ->helperText('Column value to return (usually id)'),

                                // Value Map configuration fields
                                KeyValue::make('value_map_entries')
                                    ->label('Value Mappings')
                                    ->keyLabel('API Value')
                                    ->valueLabel('Local Value')
                                    ->addActionLabel('Add Mapping')
                                    ->visible(fn (Get $get) => $get('transform') === 'value_map')
                                    ->required(fn (Get $get) => $get('transform') === 'value_map')
                                    ->afterStateHydrated(function ($state, $record, Set $set) {
                                        // Pre-populate from transform_options when editing
                                        if (! $state && $record instanceof IntegrationFieldMapping) {
                                            $options = $record->transform_options ?? [];
                                            $set('value_map_entries', $options['map'] ?? []);
                                        }
                                    })
                                    ->helperText('Map API values to local values (e.g., A → true, I → false)')
                                    ->columnSpanFull(),

                                TextInput::make('value_map_default')
                                    ->label('Default Value')
                                    ->visible(fn (Get $get) => $get('transform') === 'value_map')
                                    ->afterStateHydrated(function ($state, $record, Set $set) {
                                        // Pre-populate from transform_options when editing
                                        if ($state === null && $record instanceof IntegrationFieldMapping) {
                                            $options = $record->transform_options ?? [];
                                            $set('value_map_default', $options['default'] ?? null);
                                        }
                                    })
                                    ->helperText('Value to use when no mapping matches (leave empty to keep original)'),

                                Toggle::make('sync_on_pull')
                                    ->label(function () {
                                        $connection = $this->getOwnerRecord();

                                        return $connection->isFlatFileMethod() ? 'Include' : 'Pull';
                                    })
                                    ->default(true)
                                    ->inline(false)
                                    ->helperText(function () {
                                        $connection = $this->getOwnerRecord();

                                        return $connection->isFlatFileMethod() ? 'Include in export' : 'Pull from API';
                                    }),

                                Toggle::make('is_identifier')
                                    ->label('Primary Key')
                                    ->default(false)
                                    ->inline(false)
                                    ->helperText('Mark as record identifier'),
                            ])
                            ->columns(4)
                            ->itemLabel(function (array $state): ?string {
                                $xpath = $state['external_xpath'] ?? '';
                                $local = $state['local_field'] ?? '';
                                $table = $state['local_table'] ?? '';

                                if ($xpath && $local) {
                                    $target = $table ? "{$table}.{$local}" : $local;

                                    return "{$xpath} → {$target}";
                                }

                                if ($xpath) {
                                    return $xpath.' (fetch only)';
                                }

                                return null;
                            })
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->columns([
                TextColumn::make('object_name')
                    ->label('Object')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('display_name')
                    ->label('Display Name')
                    ->sortable(),

                TextColumn::make('local_model')
                    ->label('Local Model')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'
                    ),

                IconColumn::make('sync_enabled')
                    ->label('Enabled')
                    ->boolean(),

                TextColumn::make('sync_direction')
                    ->label('Direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pull' => 'info',
                        'push' => 'warning',
                        'bidirectional' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('field_mappings_count')
                    ->label('Fields')
                    ->counts('fieldMappings')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('last_synced_at')
                    ->label('Last Synced')
                    ->since()
                    ->placeholder('Never'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->slideOver()
                    ->modalWidth('7xl'),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('7xl'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
