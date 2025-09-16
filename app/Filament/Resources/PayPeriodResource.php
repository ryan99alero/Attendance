<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayPeriodResource\Pages;
use App\Models\PayPeriod;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Services\AttendanceProcessing\AttendanceProcessingService;
use Carbon\Carbon;


class PayPeriodResource extends Resource
{
    protected static ?string $model = PayPeriod::class;

    // Navigation Configuration
    protected static ?string $navigationGroup = 'Payroll & Overtime';
    protected static ?string $navigationLabel = 'Pay Periods';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
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

                TextColumn::make('attendance_issues_count')
                    ->label('Issues')
                    ->state(fn ($record) => $record->attendanceIssuesCount())
                    ->sortable()
                    ->url(fn ($record) => route('filament.admin.resources.attendances.index', [
                        'filter' => [
                            'is_migrated' => false,
                            'date_range' => [
                                'start' => $record->start_date->toDateString(),
                                'end' => $record->end_date->toDateString(),
                            ],
                        ],
                    ]), true)
                    ->color('danger')
                    ->size('sm')
                    ->width('auto')
                    ->alignment('center'),

                TextColumn::make('consensus_disagreement_count')
                    ->label('Engine Disc.')
                    ->state(fn ($record) => $record->consensusDisagreementCount())
                    ->sortable()
                    ->url(fn ($record) => '/admin/attendance-summary?' . http_build_query([
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
            ->headerActions([
                Action::make('generate_periods')
                    ->label('Generate PayPeriods')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Select::make('generation_type')
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

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('✅ PayPeriods Generated')
                                ->body('PayPeriods have been generated successfully. Check the output for details.')
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('❌ Generation Failed')
                                ->body("Error generating PayPeriods: {$e->getMessage()}")
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                // Process Time Button
                Action::make('process_time')
                    ->label('Process Time')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->size('sm')
                    ->extraAttributes(['data-action' => 'process_time'])
                    ->action(function ($record) {
                        set_time_limit(0); // Remove time limit for long operations

                        $processingService = app(AttendanceProcessingService::class);

                        try {
                            $processingService->processAll($record);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('✅ Processing Complete')
                                ->body("Successfully processed all attendance records for Pay Period ID: {$record->id}")
                                ->duration(8000)
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('❌ Processing Failed')
                                ->body("Error processing Pay Period ID: {$record->id}: " . substr($e->getMessage(), 0, 200))
                                ->persistent()
                                ->send();

                            throw $e; // Re-throw for proper error handling
                        }
                    }),

                // Post Time Button
                Action::make('post_time')
                    ->label('Post Time')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('Post Time Records')
                    ->modalDescription(fn ($record) =>
                        $record->is_posted
                            ? 'This pay period has already been posted and cannot be posted again.'
                            : ($record->attendanceIssuesCount() > 0 || $record->consensusDisagreementCount() > 0
                                ? 'Validation engine failed: There are punch type disagreements that require review. ' . ($record->attendanceIssuesCount() + $record->consensusDisagreementCount()) . ' unresolved issues (' . $record->attendanceIssuesCount() . ' attendance issues, ' . $record->consensusDisagreementCount() . ' engine discrepancies). Please review all issues before posting.'
                                : 'This will finalize and archive all attendance records for this pay period. This action cannot be undone.')
                    )
                    ->modalSubmitActionLabel(fn ($record) =>
                        $record->consensusDisagreementCount() > 0 ? 'Close' : 'Post Time'
                    )
                    ->disabled(fn ($record) => $record->attendanceIssuesCount() > 0 || $record->is_posted)
                    ->action(function ($record) {
                        // If there are engine discrepancies, the "Close" button was clicked - just return
                        if ($record->consensusDisagreementCount() > 0) {
                            return;
                        }

                        // Double-check for unresolved attendance issues (but not consensus - those are handled by modal)
                        if ($record->attendanceIssuesCount() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Cannot Post Time')
                                ->body('There are ' . $record->attendanceIssuesCount() . ' unresolved attendance issues. Please resolve them first.')
                                ->send();
                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('Posting Time Records')
                            ->body("Finalizing time records for Pay Period ID: {$record->id}...")
                            ->send();

                        try {
                            // Archive only properly processed records (Migrated status with punch types assigned)
                            $updatedAttendanceRecords = DB::table('attendances')
                                ->whereBetween('punch_time', [
                                    \Carbon\Carbon::parse($record->start_date)->startOfDay(),
                                    \Carbon\Carbon::parse($record->end_date)->endOfDay(),
                                ])
                                ->where('status', 'Migrated')
                                ->whereNotNull('punch_type_id')
                                ->update([
                                    'status' => 'Posted',
                                    'is_posted' => true
                                ]);

                            // Also update corresponding punch records to set is_posted = true
                            $updatedPunchRecords = DB::table('punches')
                                ->where('pay_period_id', $record->id)
                                ->update(['is_posted' => true]);

                            $record->update(['is_posted' => true]);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Time Posted Successfully')
                                ->body("Posted {$updatedAttendanceRecords} attendance records and {$updatedPunchRecords} punch records for Pay Period ID: {$record->id}")
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Post Time Error')
                                ->body("Error posting time for Pay Period ID: {$record->id}: {$e->getMessage()}")
                                ->persistent()
                                ->send();
                        }
                    }),

                // View Punches Button
                Action::make('view_punches')
                    ->label('View Punches')
                    ->color('secondary')
                    ->icon('heroicon-o-eye')
                    ->size('sm')
                    ->url(fn ($record) => route('filament.admin.resources.punches.index', ['pay_period_id' => $record->id])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayPeriods::route('/'),
            'create' => Pages\CreatePayPeriod::route('/create'),
            'edit' => Pages\EditPayPeriod::route('/{record}/edit'),
        ];
    }
}
