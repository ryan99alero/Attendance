<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayPeriodResource\Pages\CreatePayPeriod;
use App\Filament\Resources\PayPeriodResource\Pages\EditPayPeriod;
use App\Filament\Resources\PayPeriodResource\Pages\ListPayPeriods;
use App\Jobs\PostPayPeriodJob;
use App\Jobs\ProcessPayPeriodJob;
use App\Jobs\ProcessPayrollExportJob;
use App\Models\IntegrationConnection;
use App\Models\PayPeriod;
use App\Models\PayrollExport;
use App\Services\Payroll\PayrollAggregationService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class PayPeriodResource extends Resource
{
    protected static ?string $model = PayPeriod::class;

    // Navigation Configuration
    protected static string|\UnitEnum|null $navigationGroup = 'Payroll & Overtime';

    protected static ?string $navigationLabel = 'Pay Periods';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Period Name')
                ->placeholder('e.g., Week 12, Period 1')
                ->maxLength(50)
                ->helperText('Human-readable name for this pay period'),

            DatePicker::make('start_date')
                ->label('Start Date')
                ->required(),

            DatePicker::make('end_date')
                ->label('End Date')
                ->required(),

            Toggle::make('is_processed')
                ->label('Processed')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->placeholder('—')
                    ->size('sm')
                    ->width('auto')
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->size('sm')
                    ->width('auto'),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->size('sm')
                    ->width('auto')
                    ->hiddenFrom('sm'),

                IconColumn::make('is_processed')
                    ->label('Processed')
                    ->boolean()
                    ->size('sm')
                    ->width('auto'),

                IconColumn::make('is_posted')
                    ->label('Posted')
                    ->boolean()
                    ->size('sm')
                    ->width('auto'),

                TextColumn::make('processing_status')
                    ->label('Job Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): ?string => match ($state) {
                        'processing' => 'heroicon-o-arrow-path',
                        'completed' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default => null,
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'processing' => 'Processing',
                        'completed' => 'Done',
                        'failed' => 'Failed',
                        default => '—',
                    })
                    ->tooltip(fn ($record): ?string => $record->processing_error)
                    ->size('sm')
                    ->width('auto'),

                TextColumn::make('attendance_issues_count')
                    ->label('Issues')
                    ->state(fn ($record) => $record->attendanceIssuesCount())
                    ->sortable()
                    ->url(fn ($record) => '/admin/attendance-summary?'.http_build_query([
                        'payPeriodId' => $record->id,
                        'statusFilter' => 'problem_with_migrated',
                        'duplicatesFilter' => 'all',
                    ]), true)
                    ->color('danger')
                    ->size('sm')
                    ->width('auto')
                    ->alignment('center'),

                TextColumn::make('consensus_disagreement_count')
                    ->label('Engine Disc.')
                    ->state(fn ($record) => $record->consensusDisagreementCount())
                    ->sortable()
                    ->url(fn ($record) => '/admin/attendance-summary?'.http_build_query([
                        'payPeriodId' => $record->id,
                        'statusFilter' => 'all',
                        'duplicatesFilter' => 'consensus',
                    ]), true)
                    ->color('warning')
                    ->size('sm')
                    ->width('auto')
                    ->alignment('center'),

                TextColumn::make('punch_count')
                    ->label('Punches')
                    ->state(fn ($record) => $record->punchCount())
                    ->sortable()
                    ->color('success')
                    ->size('sm')
                    ->width('auto')
                    ->alignment('center'),
            ])
            ->defaultSort('start_date', 'desc')
            ->striped()
            ->poll(fn () => PayPeriod::where('processing_status', 'processing')->exists()
                || PayrollExport::processing()->exists() ? '3s' : null)
            ->headerActions([
                Action::make('generate_periods')
                    ->label('Generate PayPeriods')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->schema([
                        Select::make('generation_type')
                            ->label('Generation Type')
                            ->options([
                                'current_month' => 'Current Month Only',
                                'weeks' => '4 Weeks from Current Week',
                                'months' => '1 Month Ahead',
                            ])
                            ->default('current_month')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $generationType = $data['generation_type'];

                        try {
                            // Run the appropriate command based on selection
                            $command = match ($generationType) {
                                'current_month' => 'payroll:generate-periods --current-month',
                                'weeks' => 'payroll:generate-periods --weeks=4',
                                'months' => 'payroll:generate-periods --months=1',
                                default => 'payroll:generate-periods --current-month',
                            };

                            Artisan::call($command);
                            $output = Artisan::output();

                            Notification::make()
                                ->success()
                                ->title('✅ PayPeriods Generated')
                                ->body('PayPeriods have been generated successfully. Check the output for details.')
                                ->send();

                        } catch (Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('❌ Generation Failed')
                                ->body("Error generating PayPeriods: {$e->getMessage()}")
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                // Process Time Button
                Action::make('process_time')
                    ->label(fn ($record) => $record->isProcessing() ? 'Processing...' : 'Process Time')
                    ->color(fn ($record) => $record->isProcessing() ? 'gray' : 'primary')
                    ->icon(fn ($record) => $record->isProcessing() ? 'heroicon-o-arrow-path' : 'heroicon-o-arrow-down-on-square')
                    ->size('sm')
                    ->disabled(fn ($record) => $record->isProcessing())
                    ->action(function ($record) {
                        ProcessPayPeriodJob::dispatch($record, auth()->id());

                        Notification::make()
                            ->info()
                            ->title('Processing Started')
                            ->body("Pay Period '{$record->name}' is now being processed in the background. The page will refresh when complete.")
                            ->send();
                    }),

                // Post Time Button
                Action::make('post_time')
                    ->label('Post Time')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('Post Time Records')
                    ->modalDescription(fn ($record) => $record->is_posted
                            ? 'This pay period has already been posted and cannot be posted again.'
                            : ($record->attendanceIssuesCount() > 0 || $record->consensusDisagreementCount() > 0
                                ? 'Validation engine failed: There are punch type disagreements that require review. '.($record->attendanceIssuesCount() + $record->consensusDisagreementCount()).' unresolved issues ('.$record->attendanceIssuesCount().' attendance issues, '.$record->consensusDisagreementCount().' engine discrepancies). Please review all issues before posting.'
                                : 'This will finalize and archive all attendance records for this pay period. This action cannot be undone.')
                    )
                    ->modalSubmitActionLabel(fn ($record) => $record->consensusDisagreementCount() > 0 ? 'Close' : 'Post Time'
                    )
                    ->disabled(fn ($record) => $record->attendanceIssuesCount() > 0 || $record->is_posted)
                    ->action(function ($record) {
                        // If there are engine discrepancies, the "Close" button was clicked - just return
                        if ($record->consensusDisagreementCount() > 0) {
                            return;
                        }

                        // Double-check for unresolved attendance issues (but not consensus - those are handled by modal)
                        if ($record->attendanceIssuesCount() > 0) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot Post Time')
                                ->body('There are '.$record->attendanceIssuesCount().' unresolved attendance issues. Please resolve them first.')
                                ->send();

                            return;
                        }

                        // Dispatch job to handle posting in background
                        PostPayPeriodJob::dispatch($record, auth()->id());

                        Notification::make()
                            ->info()
                            ->title('Posting Started')
                            ->body("Pay Period '{$record->name}' is now being posted in the background. Check the Task Monitor for progress.")
                            ->send();
                    }),

                // View Punches Button
                Action::make('view_punches')
                    ->label('View Punches')
                    ->color('secondary')
                    ->icon('heroicon-o-eye')
                    ->size('sm')
                    ->url(fn ($record) => route('filament.admin.resources.punches.index', ['pay_period_id' => $record->id])),

                // Export Payroll Button
                Action::make('export_payroll')
                    ->label('Export Payroll')
                    ->color('warning')
                    ->icon('heroicon-o-document-arrow-down')
                    ->size('sm')
                    ->visible(fn ($record) => $record->is_posted)
                    ->schema([
                        Select::make('provider_id')
                            ->label('Payroll Provider')
                            ->options(fn () => IntegrationConnection::where('is_payroll_provider', true)
                                ->where('is_active', true)
                                ->pluck('name', 'id'))
                            ->required()
                            ->helperText('Select the payroll provider to export to'),

                        Select::make('format')
                            ->label('Export Format')
                            ->options([
                                'csv' => 'CSV',
                                'xlsx' => 'Excel (XLSX)',
                                'json' => 'JSON',
                                'xml' => 'XML',
                            ])
                            ->default('csv')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $provider = IntegrationConnection::findOrFail($data['provider_id']);
                            $format = $data['format'];

                            // Validate format is supported
                            if (! $provider->supportsFormat($format)) {
                                Notification::make()
                                    ->warning()
                                    ->title('Format Not Enabled')
                                    ->body("The {$format} format is not enabled for {$provider->name}. Please enable it in the integration settings.")
                                    ->send();

                                return;
                            }

                            // Aggregate data first
                            $aggregationService = app(PayrollAggregationService::class);
                            $aggregationService->aggregatePayPeriod($record);

                            // Create export record
                            $export = PayrollExport::create([
                                'pay_period_id' => $record->id,
                                'integration_connection_id' => $provider->id,
                                'format' => $format,
                                'file_name' => PayrollExport::generateFileName($provider, $record, $format),
                                'status' => PayrollExport::STATUS_PENDING,
                                'progress' => 0,
                                'progress_message' => 'Queued for processing...',
                                'exported_by' => auth()->id(),
                            ]);

                            // Dispatch job
                            ProcessPayrollExportJob::dispatch($export->id, auth()->id());

                            Notification::make()
                                ->info()
                                ->title('Export Started')
                                ->body("Export for {$provider->name} has been queued. You'll be notified when complete.")
                                ->send();

                        } catch (Exception $e) {
                            Log::error('[PayrollExport] Error: '.$e->getMessage());
                            Notification::make()
                                ->danger()
                                ->title('Export Error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // Export All Providers Button
                Action::make('export_all')
                    ->label('Export All')
                    ->color('info')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->size('sm')
                    ->visible(fn ($record) => $record->is_posted)
                    ->requiresConfirmation()
                    ->modalHeading('Export to All Payroll Providers')
                    ->modalDescription('This will generate exports for all active payroll providers in their configured formats.')
                    ->action(function ($record) {
                        try {
                            // Aggregate data first
                            $aggregationService = app(PayrollAggregationService::class);
                            $aggregationService->aggregatePayPeriod($record);

                            // Get all active providers
                            $providers = IntegrationConnection::payrollProviders()
                                ->active()
                                ->get();

                            if ($providers->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('No Providers')
                                    ->body('No active payroll providers configured.')
                                    ->send();

                                return;
                            }

                            $exportCount = 0;

                            foreach ($providers as $provider) {
                                foreach ($provider->getEnabledFormats() as $format) {
                                    // Create export record
                                    $export = PayrollExport::create([
                                        'pay_period_id' => $record->id,
                                        'integration_connection_id' => $provider->id,
                                        'format' => $format,
                                        'file_name' => PayrollExport::generateFileName($provider, $record, $format),
                                        'status' => PayrollExport::STATUS_PENDING,
                                        'progress' => 0,
                                        'progress_message' => 'Queued for processing...',
                                        'exported_by' => auth()->id(),
                                    ]);

                                    // Dispatch job
                                    ProcessPayrollExportJob::dispatch($export->id, auth()->id());
                                    $exportCount++;
                                }
                            }

                            Notification::make()
                                ->info()
                                ->title('Exports Queued')
                                ->body("{$exportCount} export job(s) have been queued. You'll be notified as they complete.")
                                ->send();

                        } catch (Exception $e) {
                            Log::error('[PayrollExport] Bulk export error: '.$e->getMessage());
                            Notification::make()
                                ->danger()
                                ->title('Export Error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // View Export Status / Downloads
                Action::make('view_exports')
                    ->label(fn ($record) => self::getExportStatusLabel($record))
                    ->color(fn ($record) => self::getExportStatusColor($record))
                    ->icon(fn ($record) => self::getExportStatusIcon($record))
                    ->size('sm')
                    ->visible(fn ($record) => PayrollExport::where('pay_period_id', $record->id)->exists())
                    ->modalHeading('Payroll Exports')
                    ->modalIcon(null)
                    ->modalContent(fn ($record) => view('filament.modals.payroll-exports', [
                        'exports' => PayrollExport::where('pay_period_id', $record->id)
                            ->with('integrationConnection')
                            ->orderBy('created_at', 'desc')
                            ->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('6xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayPeriods::route('/'),
            'create' => CreatePayPeriod::route('/create'),
            'edit' => EditPayPeriod::route('/{record}/edit'),
        ];
    }

    /**
     * Get export status label for the action button
     */
    protected static function getExportStatusLabel(PayPeriod $record): string
    {
        $exports = PayrollExport::where('pay_period_id', $record->id)->get();

        if ($exports->isEmpty()) {
            return 'No Exports';
        }

        $processing = $exports->filter(fn ($e) => $e->isProcessing())->count();
        $completed = $exports->filter(fn ($e) => $e->isCompleted())->count();

        if ($processing > 0) {
            $current = $exports->firstWhere('status', PayrollExport::STATUS_PROCESSING);

            return $current ? "{$current->progress}%" : 'Processing...';
        }

        return "Downloads ({$completed})";
    }

    /**
     * Get export status color for the action button
     */
    protected static function getExportStatusColor(PayPeriod $record): string
    {
        $exports = PayrollExport::where('pay_period_id', $record->id)->get();

        if ($exports->contains(fn ($e) => $e->isProcessing())) {
            return 'warning';
        }

        if ($exports->contains(fn ($e) => $e->isFailed())) {
            return 'danger';
        }

        return 'success';
    }

    /**
     * Get export status icon for the action button
     */
    protected static function getExportStatusIcon(PayPeriod $record): string
    {
        $exports = PayrollExport::where('pay_period_id', $record->id)->get();

        if ($exports->contains(fn ($e) => $e->isProcessing())) {
            return 'heroicon-o-arrow-path';
        }

        return 'heroicon-o-arrow-down-tray';
    }
}
