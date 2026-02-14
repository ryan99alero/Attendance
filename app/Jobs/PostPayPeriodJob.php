<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\SystemLog;
use App\Models\SystemTask;
use App\Models\User;
use App\Models\VacationCalendar;
use App\Models\VacationTransaction;
use App\Services\AttendanceProcessing\PunchMigrationService;
use App\Traits\TracksSystemTask;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class PostPayPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksSystemTask;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public PayPeriod $payPeriod,
        public ?int $userId = null
    ) {}

    public function handle(PunchMigrationService $migrationService): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        // Create system task for tracking
        $this->initializeSystemTask(
            type: SystemTask::TYPE_EXPORT,
            name: "Post Time: {$this->payPeriod->name}",
            description: "Posting time records for {$this->payPeriod->start_date->format('M j')} - {$this->payPeriod->end_date->format('M j, Y')}",
            relatedModel: PayPeriod::class,
            relatedId: $this->payPeriod->id,
            userId: $this->userId
        );

        $log = SystemLog::logEvent(
            type: 'pay_period_posting',
            summary: "Posting Pay Period: {$this->payPeriod->name}",
            level: SystemLog::LEVEL_INFO,
            metadata: ['pay_period_id' => $this->payPeriod->id],
            context: $this->payPeriod
        );
        $log->update(['status' => SystemLog::STATUS_RUNNING, 'started_at' => now(), 'user_id' => $this->userId]);

        // Initialize progress tracking
        $this->payPeriod->update([
            'processing_status' => 'posting',
            'processing_progress' => 5,
            'processing_message' => 'Starting post process...',
            'processing_started_at' => now(),
            'processing_error' => null,
        ]);

        try {
            $this->updateTaskProgress(10, 'Migrating attendance records to punches...');
            $this->payPeriod->updateProgress(10, 'Migrating attendance records to punches...');

            // Step 1: Migrate attendance records to punches table
            $migrationService->migratePunchesWithinPayPeriod($this->payPeriod);

            $this->updateTaskProgress(50, 'Archiving attendance records...');
            $this->payPeriod->updateProgress(50, 'Archiving attendance records...');

            // Step 2: Archive migrated records
            $updatedAttendanceRecords = DB::table('attendances')
                ->whereBetween('punch_time', [
                    Carbon::parse($this->payPeriod->start_date)->startOfDay(),
                    Carbon::parse($this->payPeriod->end_date)->endOfDay(),
                ])
                ->where('status', 'Migrated')
                ->whereNotNull('punch_type_id')
                ->update([
                    'status' => 'Posted',
                    'is_posted' => true,
                    'is_archived' => true,
                ]);

            $this->updateTaskProgress(70, 'Updating punch records...');
            $this->payPeriod->updateProgress(70, 'Updating punch records...');

            // Step 3: Update punch records
            $updatedPunchRecords = DB::table('punches')
                ->where('pay_period_id', $this->payPeriod->id)
                ->update([
                    'is_posted' => true,
                    'is_archived' => true,
                ]);

            $this->updateTaskProgress(85, 'Processing vacation deductions...');
            $this->payPeriod->updateProgress(85, 'Processing vacation deductions...');

            // Step 4: Process vacation deductions
            $vacationDeductions = $this->processVacationDeductions();

            // Step 5: Mark pay period as posted
            $this->payPeriod->update([
                'is_posted' => true,
                'processing_status' => 'completed',
                'processing_progress' => 100,
                'processing_message' => 'Posting complete',
                'processing_completed_at' => now(),
            ]);

            $log->markSuccess();
            $this->completeTask('Posting complete');

            // Notify user
            $this->notifyUser(true, null, $updatedAttendanceRecords, $updatedPunchRecords, $vacationDeductions);

        } catch (Throwable $e) {
            $this->payPeriod->update([
                'processing_status' => 'failed',
                'processing_message' => 'Posting failed',
                'processing_error' => $e->getMessage(),
                'processing_completed_at' => now(),
            ]);

            $log->markFailed($e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->failTask($e->getMessage());

            // Notify user of failure
            $this->notifyUser(false, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Process vacation time deductions for posted records.
     * Creates proper VacationTransaction records for audit trail.
     */
    protected function processVacationDeductions(): int
    {
        $vacationClassificationId = DB::table('classifications')->where('code', 'VACATION')->value('id');

        if (! $vacationClassificationId) {
            return 0;
        }

        // Find all vacation attendance records that were just posted
        $vacationRecords = Attendance::whereBetween('punch_time', [
            Carbon::parse($this->payPeriod->start_date)->startOfDay(),
            Carbon::parse($this->payPeriod->end_date)->endOfDay(),
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

            if (! isset($employeeVacationHours[$employeeId])) {
                $employeeVacationHours[$employeeId] = [];
            }

            if (! isset($employeeVacationHours[$employeeId][$date])) {
                // Calculate actual hours from punch times for this date
                $vacationPunchesForDate = $vacationRecords->where('employee_id', $employeeId)
                    ->filter(fn ($r) => Carbon::parse($r->punch_time)->toDateString() === $date);

                $clockInTimes = $vacationPunchesForDate->where('punch_state', 'start')->pluck('punch_time');
                $clockOutTimes = $vacationPunchesForDate->where('punch_state', 'stop')->pluck('punch_time');

                $totalHours = 0;

                // Calculate hours from paired clock in/out times
                foreach ($clockInTimes as $index => $clockIn) {
                    if (isset($clockOutTimes[$index])) {
                        $start = Carbon::parse($clockIn);
                        $end = Carbon::parse($clockOutTimes[$index]);
                        $totalHours += $end->diffInHours($start, true);
                    }
                }

                // Fallback to traditional calculation if no paired punches found
                if ($totalHours == 0) {
                    $dailyHours = $record->employee->shiftSchedule->daily_hours ?? 8.0;

                    // Check if this is a half-day vacation
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
                    $description = "Vacation usage - {$date}";
                    if ($hoursUsed < 8) {
                        $description .= ' (half day)';
                    }

                    VacationTransaction::createUsageTransaction(
                        $employeeId,
                        $this->payPeriod->id,
                        $hoursUsed,
                        $date,
                        $description
                    );
                }
            }

            if (! empty($dailyHours)) {
                $employeesUpdated++;
            }
        }

        return $employeesUpdated;
    }

    protected function notifyUser(bool $success, ?string $errorMessage = null, int $attendanceCount = 0, int $punchCount = 0, int $vacationDeductions = 0): void
    {
        if (! $this->userId) {
            return;
        }

        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        if ($success) {
            $body = "Posted {$attendanceCount} attendance records and {$punchCount} punch records for Pay Period '{$this->payPeriod->name}'.";
            if ($vacationDeductions > 0) {
                $body .= " Deducted vacation time for {$vacationDeductions} employees.";
            }

            Notification::make()
                ->success()
                ->title('Pay Period Posted Successfully')
                ->body($body)
                ->sendToDatabase($user);
        } else {
            Notification::make()
                ->danger()
                ->title('Pay Period Posting Failed')
                ->body("Pay Period '{$this->payPeriod->name}' failed: ".($errorMessage ?? 'Unknown error'))
                ->sendToDatabase($user);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->payPeriod->update([
            'processing_status' => 'failed',
            'processing_message' => 'Posting failed',
            'processing_error' => $exception->getMessage(),
            'processing_completed_at' => now(),
        ]);

        $this->failTask($exception->getMessage());
        $this->notifyUser(false, $exception->getMessage());
    }
}
