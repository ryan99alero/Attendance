<?php

namespace App\Filament\Resources\IntegrationConnectionResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\IntegrationFieldMapping;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Services\Integrations\PaceApiClient;
use App\Services\ModelDiscoveryService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class IntegrationObjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'objects';

    protected static ?string $title = 'Objects';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-cube';

    public function form(Schema $schema): Schema
    {
        $discoveryService = new ModelDiscoveryService();

        return $schema
            ->components([
                // Section 1: Object Definition
                Section::make('Object Definition')
                    ->schema([
                        Select::make('object_name')
                            ->label('Object Name')
                            ->options(fn () => (new PaceApiClient(
                                $this->getOwnerRecord()
                            ))->getCommonObjectTypes())
                            ->searchable()
                            ->required()
                            ->helperText('Select a Pace object type or enter a custom name'),

                        TextInput::make('display_name')
                            ->label('Display Name')
                            ->required()
                            ->maxLength(100),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        TextInput::make('primary_key_field')
                            ->label('API Primary Key XPath')
                            ->default('@id')
                            ->required()
                            ->helperText('XPath to the source API\'s primary key (e.g. @id). Used when fetching a single record.'),

                        Select::make('primary_key_type')
                            ->label('API Primary Key Type')
                            ->options([
                                'String' => 'String',
                                'Integer' => 'Integer',
                            ])
                            ->default('String')
                            ->required()
                            ->helperText('Data type as returned by the source API'),

                        Select::make('local_model')
                            ->label('Local Model')
                            ->options(fn () => $discoveryService->getModelOptionsForSelect())
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state && class_exists($state)) {
                                    $model = new $state;
                                    $set('local_table', $model->getTable());
                                }
                            }),

                        TextInput::make('local_table')
                            ->label('Local Table')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('default_filter')
                            ->label('Default Filter')
                            ->placeholder("@status = 'A'")
                            ->helperText('Default XPath filter expression for syncs')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Section 2: Sync Settings
                Section::make('Sync Settings')
                    ->schema([
                        Toggle::make('sync_enabled')
                            ->label('Sync Enabled')
                            ->default(false),

                        Select::make('sync_direction')
                            ->label('Sync Direction')
                            ->options([
                                'pull' => 'Pull (API → Local)',
                                'push' => 'Push (Local → API)',
                                'bidirectional' => 'Bidirectional',
                            ])
                            ->default('pull')
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
                            ->required()
                            ->helperText('Pace API method to use for this object'),

                        Select::make('sync_frequency')
                            ->label('Sync Frequency')
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
                Section::make('Field Mappings')
                    ->schema([
                        Repeater::make('fieldMappings')
                            ->relationship()
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                // Auto-derive external_field from local_field (or xpath basename)
                                if (empty($data['external_field'])) {
                                    $data['external_field'] = $data['local_field']
                                        ?: ltrim(basename($data['external_xpath'] ?? ''), '@');
                                }
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                // Auto-derive external_field from local_field (or xpath basename)
                                if (empty($data['external_field'])) {
                                    $data['external_field'] = $data['local_field']
                                        ?: ltrim(basename($data['external_xpath'] ?? ''), '@');
                                }
                                return $data;
                            })
                            ->schema([
                                TextInput::make('external_xpath')
                                    ->label('API Field (XPath)')
                                    ->placeholder('@firstName')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Pace field path (e.g. @firstName, /country/@isoCountry)'),

                                Hidden::make('external_field'),

                                Select::make('external_type')
                                    ->label('External Type')
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

                                        if (!$localModel || !class_exists($localModel)) {
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
                                        'trim' => 'Trim',
                                        'uppercase' => 'Uppercase',
                                        'lowercase' => 'Lowercase',
                                        'json_decode' => 'JSON Decode',
                                    ])
                                    ->placeholder('None')
                                    ->dehydrateStateUsing(fn ($state) => $state ?: null)
                                    ->nullable(),

                                Toggle::make('sync_on_pull')
                                    ->label('Pull')
                                    ->default(true)
                                    ->inline(false),

                                Toggle::make('is_identifier')
                                    ->label('Identifier')
                                    ->default(false)
                                    ->inline(false),
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
                                    return $xpath . ' (fetch only)';
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
                    ->formatStateUsing(fn (?string $state): string =>
                        $state ? class_basename($state) : '—'
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
