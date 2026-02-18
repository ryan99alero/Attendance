<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemLogResource\Pages;
use App\Models\CompanySetup;
use App\Models\SystemLog;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SystemLogResource extends Resource
{
    protected static ?string $model = SystemLog::class;

    protected static ?string $navigationLabel = 'System Logs';

    protected static string|\UnitEnum|null $navigationGroup = 'System & Hardware';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Log Details')
                ->schema([
                    Placeholder::make('category')
                        ->label('Category')
                        ->content(fn ($record) => ucfirst($record->category)),

                    Placeholder::make('type')
                        ->label('Type')
                        ->content(fn ($record) => $record->type),

                    Placeholder::make('level')
                        ->label('Level')
                        ->content(fn ($record) => ucfirst($record->level)),

                    Placeholder::make('status')
                        ->label('Status')
                        ->content(fn ($record) => $record->status ? ucfirst($record->status) : 'N/A'),

                    Placeholder::make('summary')
                        ->label('Summary')
                        ->content(fn ($record) => $record->summary)
                        ->columnSpanFull(),

                    Placeholder::make('user_name')
                        ->label('Triggered By')
                        ->content(fn ($record) => $record->user?->name ?? 'System'),

                    Placeholder::make('duration')
                        ->label('Duration')
                        ->content(fn ($record) => $record->getDurationForHumans()),
                ])
                ->columns(3),

            Section::make('Timing')
                ->schema([
                    Placeholder::make('started_at')
                        ->label('Started At')
                        ->content(fn ($record) => $record->started_at?->format('Y-m-d H:i:s') ?? 'N/A'),

                    Placeholder::make('completed_at')
                        ->label('Completed At')
                        ->content(fn ($record) => $record->completed_at?->format('Y-m-d H:i:s') ?? 'In Progress'),

                    Placeholder::make('created_at')
                        ->label('Logged At')
                        ->content(fn ($record) => $record->created_at?->format('Y-m-d H:i:s')),
                ])
                ->columns(3),

            Section::make('Record Counts')
                ->schema([
                    Placeholder::make('records_fetched')
                        ->label('Fetched')
                        ->content(fn ($record) => number_format($record->counts['fetched'] ?? 0)),

                    Placeholder::make('records_created')
                        ->label('Created')
                        ->content(fn ($record) => number_format($record->counts['created'] ?? 0)),

                    Placeholder::make('records_updated')
                        ->label('Updated')
                        ->content(fn ($record) => number_format($record->counts['updated'] ?? 0)),

                    Placeholder::make('records_skipped')
                        ->label('Skipped')
                        ->content(fn ($record) => number_format($record->counts['skipped'] ?? 0)),

                    Placeholder::make('records_failed')
                        ->label('Failed')
                        ->content(fn ($record) => number_format($record->counts['failed'] ?? 0)),
                ])
                ->columns(5)
                ->visible(fn ($record) => ! empty($record->counts)),

            Section::make('Error Information')
                ->schema([
                    Placeholder::make('error_message')
                        ->label('Error Message')
                        ->content(fn ($record) => $record->error_message ?? 'None')
                        ->columnSpanFull(),

                    Placeholder::make('error_details_display')
                        ->label('Error Details')
                        ->content(function ($record) {
                            if (empty($record->error_details)) {
                                return 'None';
                            }

                            return json_encode($record->error_details, JSON_PRETTY_PRINT);
                        })
                        ->columnSpanFull(),
                ])
                ->visible(fn ($record) => $record->status === 'failed' || $record->status === 'partial'),

            Section::make('Request Data')
                ->schema([
                    Placeholder::make('request_data_display')
                        ->label('Request Payload')
                        ->content(function ($record) {
                            if (empty($record->request_data)) {
                                return 'None';
                            }

                            return json_encode($record->request_data, JSON_PRETTY_PRINT);
                        })
                        ->columnSpanFull(),
                ])
                ->visible(fn ($record) => ! empty($record->request_data))
                ->collapsed(),

            Section::make('Response Data')
                ->schema([
                    Placeholder::make('response_data_display')
                        ->label('Response')
                        ->content(function ($record) {
                            if (empty($record->response_data)) {
                                return 'None';
                            }

                            return json_encode($record->response_data, JSON_PRETTY_PRINT);
                        })
                        ->columnSpanFull(),
                ])
                ->visible(fn ($record) => ! empty($record->response_data))
                ->collapsed(),

            Section::make('Metadata')
                ->schema([
                    Placeholder::make('metadata_display')
                        ->label('Additional Data')
                        ->content(function ($record) {
                            if (empty($record->metadata)) {
                                return 'None';
                            }

                            return json_encode($record->metadata, JSON_PRETTY_PRINT);
                        })
                        ->columnSpanFull(),

                    Placeholder::make('ip_address')
                        ->label('IP Address')
                        ->content(fn ($record) => $record->ip_address ?? 'N/A'),

                    Placeholder::make('user_agent')
                        ->label('User Agent')
                        ->content(fn ($record) => $record->user_agent ?? 'N/A')
                        ->visible(fn ($record) => ! empty($record->user_agent)),
                ])
                ->visible(fn ($record) => ! empty($record->metadata) || ! empty($record->ip_address))
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date/Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'integration' => 'primary',
                        'api' => 'info',
                        'system' => 'gray',
                        'device' => 'warning',
                        'user' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('type')
                    ->label('Type')
                    ->searchable(),

                TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'debug' => 'gray',
                        'info' => 'info',
                        'warning' => 'warning',
                        'error' => 'danger',
                        'critical' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'partial' => 'warning',
                        'running' => 'info',
                        'pending' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '-'),

                TextColumn::make('summary')
                    ->label('Summary')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->summary)
                    ->searchable(),

                TextColumn::make('duration_display')
                    ->label('Duration')
                    ->getStateUsing(fn ($record) => $record->getDurationForHumans()),

                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->visible(fn ($record) => ! empty($record->error_message))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'integration' => 'Integration',
                        'api' => 'API',
                        'system' => 'System',
                        'device' => 'Device',
                        'user' => 'User',
                        'error' => 'Error',
                    ]),

                SelectFilter::make('level')
                    ->label('Level')
                    ->options([
                        'debug' => 'Debug',
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'critical' => 'Critical',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'partial' => 'Partial',
                        'running' => 'Running',
                        'pending' => 'Pending',
                    ]),

                Filter::make('has_errors')
                    ->label('Has Errors')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('error_message')),

                Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', Carbon::today())),

                Filter::make('last_7_days')
                    ->label('Last 7 Days')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', Carbon::now()->subDays(7))),

                Filter::make('last_30_days')
                    ->label('Last 30 Days')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', Carbon::now()->subDays(30))),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemLogs::route('/'),
            'view' => Pages\ViewSystemLog::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $failedCount = static::getModel()::where('status', 'failed')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        return $failedCount > 0 ? (string) $failedCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        // Filter logs by minimum level from company settings
        $query = parent::getEloquentQuery();

        $logLevel = CompanySetup::first()?->logging_level ?? 'info';

        return $query->byMinLevel($logLevel);
    }
}
