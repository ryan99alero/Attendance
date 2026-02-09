<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Exception;
use App\Models\Attendance;
use App\Models\VacationCalendar;
use App\Models\VacationTransaction;
use Illuminate\Support\Facades\Log;
use App\Filament\Resources\PayPeriodResource\Pages\ListPayPeriods;
use App\Filament\Resources\PayPeriodResource\Pages\CreatePayPeriod;
use App\Filament\Resources\PayPeriodResource\Pages\EditPayPeriod;
use UnitEnum;
use BackedEnum;

use App\Filament\Resources\PayPeriodResource\Pages;
use App\Models\PayPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
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
    protected static string | \UnitEnum | null $navigationGroup = 'Payroll & Overtime';
    protected static ?string $navigationLabel = 'Pay Periods';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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

                            Notification::make()
                                ->success()
                                ->title('✅ Processing Complete')
                                ->body("Successfully processed all attendance records for Pay Period ID: {$record->id}")
                                ->duration(8000)
                                ->send();

                        } catch (Exception $e) {
                            Notification::make()
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
                            Notification::make()
                                ->danger()
                                ->title('Cannot Post Time')
                                ->body('There are ' . $record->attendanceIssuesCount() . ' unresolved attendance issues. Please resolve them first.')
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->info()
                            ->title('Posting Time Records')
                            ->body("Finalizing time records for Pay Period ID: {$record->id}...")
                            ->send();

                        try {
                            // Archive only properly processed records (Migrated status with punch types assigned)
                            $updatedAttendanceRecords = DB::table('attendances')
                                ->whereBetween('punch_time', [
                                    Carbon::parse($record->start_date)->startOfDay(),
                                    Carbon::parse($record->end_date)->endOfDay(),
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

                            // Automatically deduct vacation time for posted vacation records
                            $vacationDeductions = self::processVacationDeductions($record);

                            $successMessage = "Posted {$updatedAttendanceRecords} attendance records and {$updatedPunchRecords} punch records for Pay Period ID: {$record->id}";
                            if ($vacationDeductions > 0) {
                                $successMessage .= " | Deducted vacation time for {$vacationDeductions} employees";
                            }

                            Notification::make()
                                ->success()
                                ->title('Time Posted Successfully')
                                ->body($successMessage)
                                ->send();

                        } catch (Exception $e) {
                            Notification::make()
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

    /**
     * Process vacation deductions for posted vacation records
     */
    private static function processVacationDeductions(PayPeriod $payPeriod): int
    {
        // Get vacation classification ID
        $vacationClassificationId = DB::table('classifications')->where('code', 'VACATION')->value('id');

        // Find all vacation attendance records that were just posted
        $vacationRecords = Attendance::whereBetween('punch_time', [
                Carbon::parse($payPeriod->start_date)->startOfDay(),
                Carbon::parse($payPeriod->end_date)->endOfDay(),
            ])
            ->where('classification_id', $vacationClassificationId)
            ->where('status', 'Posted')
            ->with('employee.shiftSchedule')
            ->get();

        if ($vacationRecords->isEmpty()) {
            return 0;
        }

        // Group vacation records by employee and date to calculate actual hours
        $employeeVacationHours = [];

        foreach ($vacationRecords as $record) {
            $employeeId = $record->employee_id;
            $date = Carbon::parse($record->punch_time)->toDateString();

            if (!isset($employeeVacationHours[$employeeId])) {
                $employeeVacationHours[$employeeId] = [];
            }

            if (!isset($employeeVacationHours[$employeeId][$date])) {
                // Calculate actual hours from punch times for this date
                $vacationPunchesForDate = $vacationRecords->where('employee_id', $employeeId)
                    ->filter(function($r) use ($date) {
                        return Carbon::parse($r->punch_time)->toDateString() === $date;
                    });

                $clockInTimes = $vacationPunchesForDate->where('punch_state', 'start')->pluck('punch_time');
                $clockOutTimes = $vacationPunchesForDate->where('punch_state', 'stop')->pluck('punch_time');

                $totalHours = 0;

                // Calculate hours from paired clock in/out times
                foreach ($clockInTimes as $index => $clockIn) {
                    if (isset($clockOutTimes[$index])) {
                        $start = Carbon::parse($clockIn);
                        $end = Carbon::parse($clockOutTimes[$index]);
                        $totalHours += $end->diffInHours($start, true); // true for absolute value
                    }
                }

                // Fallback to traditional calculation if no paired punches found
                if ($totalHours == 0) {
                    // Get daily hours from shift schedule or default to 8 hours
                    $dailyHours = $record->employee->shiftSchedule->daily_hours ?? 8.0;

                    // Check if this is a half-day vacation (from VacationCalendar)
                    $vacationCalendar = VacationCalendar::where('employee_id', $employeeId)
                        ->whereDate('vacation_date', $date)
                        ->first();

                    if ($vacationCalendar && $vacationCalendar->is_half_day) {
                        $totalHours = $dailyHours / 2;
                    } else {
                        $totalHours = $dailyHours;
                    }
                }

                $employeeVacationHours[$employeeId][$date] = $totalHours;
            }
        }

        // Create vacation usage transactions for each employee
        $employeesUpdated = 0;

        foreach ($employeeVacationHours as $employeeId => $dailyHours) {
            foreach ($dailyHours as $date => $hoursUsed) {
                if ($hoursUsed > 0) {
                    // Create vacation usage transaction
                    $description = "Vacation usage - {$date}";
                    if ($hoursUsed < 8) {
                        $description .= " (half day)";
                    }

                    VacationTransaction::createUsageTransaction(
                        $employeeId,
                        $payPeriod->id,
                        $hoursUsed,
                        $date,
                        $description
                    );

                    Log::info("VacationTransaction: Created usage transaction - {$hoursUsed} hours for Employee ID {$employeeId} on {$date}");
                }
            }

            if (!empty($dailyHours)) {
                $employeesUpdated++;
            }
        }

        return $employeesUpdated;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayPeriods::route('/'),
            'create' => CreatePayPeriod::route('/create'),
            'edit' => EditPayPeriod::route('/{record}/edit'),
        ];
    }
}
