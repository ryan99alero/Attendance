<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ClockEventExporter;
use App\Filament\Resources\ClockEventResource\Pages\CreateClockEvent;
use App\Filament\Resources\ClockEventResource\Pages\EditClockEvent;
use App\Filament\Resources\ClockEventResource\Pages\ListClockEvents;
use App\Models\ClockEvent;
use App\Models\Credential;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClockEventResource extends Resource
{
    protected static ?string $model = ClockEvent::class;

    // Navigation Configuration
    protected static string|\UnitEnum|null $navigationGroup = 'Time Tracking';

    protected static ?string $navigationLabel = 'Clock Events';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = -95;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_names')
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('credential_id', null))
                    ->nullable(),

                Select::make('device_id')
                    ->label('Device')
                    ->relationship('device', 'device_name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('credential_id')
                    ->label('Credential')
                    ->options(function (Get $get) {
                        $employeeId = $get('employee_id');
                        if (! $employeeId) {
                            return [];
                        }

                        return Credential::where('employee_id', $employeeId)
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn ($cred) => [$cred->id => "{$cred->identifier} {$cred->label}"]);
                    })
                    ->searchable()
                    ->preload()
                    ->required(),

                DateTimePicker::make('event_time')
                    ->label('Event Time')
                    ->required(),

                DatePicker::make('shift_date')
                    ->label('Shift Date')
                    ->nullable(),

                Select::make('event_source')
                    ->label('Event Source')
                    ->options([
                        'device' => 'Device',
                        'api' => 'API',
                        'backfill' => 'Backfill',
                        'admin' => 'Admin',
                    ])
                    ->default('admin')
                    ->required(),

                TextInput::make('location')
                    ->label('Location')
                    ->maxLength(255),

                TextInput::make('confidence')
                    ->label('Confidence')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),

                Textarea::make('notes')
                    ->label('Notes')
                    ->maxLength(1000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('employee.full_names')
                    ->label('Employee')
                    ->sortable()
                    ->searchable()
                    ->placeholder('Unknown'),

                TextColumn::make('device.device_name')
                    ->label('Device')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('credential')
                    ->label('Credential')
                    ->state(fn ($record) => $record->credential ? "{$record->credential->identifier} {$record->credential->label}" : null)
                    ->placeholder('None')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('credential_id', $direction)),

                TextColumn::make('event_time')
                    ->label('Event Time')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('shift_date')
                    ->label('Shift Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('event_source')
                    ->label('Source')
                    ->sortable(),

                TextColumn::make('confidence')
                    ->label('Confidence')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('processing_error')
                    ->label('Error')
                    ->placeholder('None')
                    ->color('danger')
                    ->wrap()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->processing_error)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('device_id')
                    ->label('Device')
                    ->relationship('device', 'device_name')
                    ->preload(),

                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_names'),

                Filter::make('has_errors')
                    ->label('Has Processing Errors')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('processing_error'))
                    ->toggle(),

                Filter::make('ready_for_processing')
                    ->label('Ready for Processing')
                    ->query(fn (Builder $query): Builder => $query->readyForProcessing())
                    ->toggle(),

                Filter::make('event_time')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<ClockEvent> $query */
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('event_time', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('event_time', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ClockEventExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('event_time', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClockEvents::route('/'),
            'create' => CreateClockEvent::route('/create'),
            'edit' => EditClockEvent::route('/{record}/edit'),
        ];
    }
}
