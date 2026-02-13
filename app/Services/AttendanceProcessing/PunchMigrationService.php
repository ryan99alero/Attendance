<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\Punch;
use App\Services\RoundingRuleService;
use App\Services\TimeGrouping\AttendanceTimeGroupService;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PunchMigrationService
{
    protected RoundingRuleService $roundingRuleService;

    protected ?int $vacationClassificationId = null;

    protected ?int $clockInTypeId = null;

    protected ?int $clockOutTypeId = null;

    protected ?int $unknownTypeId = null;

    protected ?int $holidayClassificationId = null;

    /**
     * Cache for employee round_group_id lookups
     */
    protected array $employeeRoundGroupCache = [];

    public function __construct(RoundingRuleService $roundingRuleService)
    {
        $this->roundingRuleService = $roundingRuleService;
    }

    /**
     * Cache lookup values to avoid repeated queries.
     */
    protected function cacheLookupValues(): void
    {
        $this->vacationClassificationId = DB::table('classifications')->where('code', 'VACATION')->value('id');
        $this->holidayClassificationId = DB::table('classifications')->where('name', 'Holiday')->value('id');
        $this->clockInTypeId = DB::table('punch_types')->where('name', 'Clock In')->value('id');
        $this->clockOutTypeId = DB::table('punch_types')->where('name', 'Clock Out')->value('id');
        $this->unknownTypeId = DB::table('punch_types')->where('name', 'Unknown')->value('id');
    }

    /**
     * Get employee round_group_id from cache or database.
     */
    protected function getEmployeeRoundGroupId(int $employeeId): ?int
    {
        if (! isset($this->employeeRoundGroupCache[$employeeId])) {
            $this->employeeRoundGroupCache[$employeeId] = DB::table('employees')
                ->where('id', $employeeId)
                ->value('round_group_id');
        }

        return $this->employeeRoundGroupCache[$employeeId];
    }

    /**
     * Migrate punches to the Punches table and mark as migrated.
     */
    public function migratePunchesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        // Cache lookup values once
        $this->cacheLookupValues();

        Log::info("PunchMigrationService: Starting migration for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        // Count for logging
        $totalCount = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->where('status', 'Complete')
            ->count();

        Log::info("PunchMigrationService: Found {$totalCount} records for PayPeriod ID: {$payPeriod->id}");

        $processed = 0;
        $errors = 0;

        // Use cursor() to iterate one record at a time - prevents memory exhaustion
        foreach (Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->where('status', 'Complete')
            ->cursor() as $attendance) {
            try {
                $this->processAttendanceRecord($attendance, $payPeriod);
                $processed++;
            } catch (Exception $e) {
                $errors++;
                Log::error("PunchMigrationService: Error for Attendance ID {$attendance->id}: ".$e->getMessage());
            }
        }

        Log::info("PunchMigrationService: Completed for PayPeriod ID: {$payPeriod->id} - Processed: {$processed}, Errors: {$errors}");

        // Check if all records are properly migrated
        $this->checkAndUpdatePayPeriodProcessedStatus($payPeriod);
    }

    /**
     * Process a single attendance record.
     */
    protected function processAttendanceRecord(Attendance $attendance, PayPeriod $payPeriod): void
    {
        // Identify Holiday and Vacation Attendance
        $isHolidayRecord = ! is_null($attendance->holiday_id);
        $isVacationRecord = $attendance->classification_id === $this->vacationClassificationId;

        // Get employee round_group_id from cache to avoid N+1
        $roundGroupId = $this->getEmployeeRoundGroupId($attendance->employee_id);
        $roundedPunchTime = $roundGroupId
            ? $this->roundingRuleService->getRoundedTime(new DateTime($attendance->punch_time), $roundGroupId)
            : new DateTime($attendance->punch_time);

        $externalGroupId = $attendance->external_group_id;

        app(AttendanceTimeGroupService::class)->getOrCreateGroupAndAssign($attendance, auth()->id() ?? 0);

        if (empty($attendance->shift_date)) {
            return;
        }

        if (! $isHolidayRecord && ! $isVacationRecord && ! $this->hasStartAndStopTime($attendance->employee_id, $attendance->punch_time)) {
            return;
        }

        // Skip individual unpaired punches
        if ($attendance->status === 'NeedsReview' && str_contains($attendance->issue_notes ?? '', 'Unpaired punch')) {
            return;
        }

        DB::beginTransaction();

        try {
            $punchTypeId = $attendance->punch_type_id ?? $this->unknownTypeId;

            $classificationId = $attendance->classification_id;
            if ($isHolidayRecord && is_null($classificationId)) {
                $classificationId = $this->holidayClassificationId;
            } elseif ($isVacationRecord && is_null($classificationId)) {
                $classificationId = $this->vacationClassificationId;
            }

            $punch = Punch::create([
                'employee_id' => $attendance->employee_id,
                'device_id' => $attendance->device_id,
                'punch_type_id' => $punchTypeId,
                'punch_state' => $attendance->punch_state,
                'punch_time' => $roundedPunchTime->format('Y-m-d H:i:s'),
                'is_altered' => true,
                'pay_period_id' => $payPeriod->id,
                'attendance_id' => $attendance->id,
                'external_group_id' => $externalGroupId,
                'shift_date' => $attendance->shift_date,
                'classification_id' => $classificationId,
            ]);

            if (! $punch) {
                DB::rollBack();

                return;
            }

            $attendance->update([
                'status' => 'Migrated',
                'is_migrated' => true,
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Ensure at least one Clock In and one Clock Out punch exists per day before migration.
     */
    private function hasStartAndStopTime(int $employeeId, string $punchTime): bool
    {
        $date = Carbon::parse($punchTime)->toDateString();

        // Check for holiday records using count instead of get
        $hasHolidayRecords = Attendance::where('employee_id', $employeeId)
            ->whereDate('punch_time', $date)
            ->whereNotNull('holiday_id')
            ->whereIn('status', ['Complete', 'Migrated'])
            ->exists();

        if ($hasHolidayRecords) {
            return true;
        }

        // Check for vacation records
        $vacationCount = Attendance::where('employee_id', $employeeId)
            ->whereDate('punch_time', $date)
            ->where('classification_id', $this->vacationClassificationId)
            ->whereIn('status', ['Complete', 'Migrated'])
            ->whereIn('punch_type_id', [$this->clockInTypeId, $this->clockOutTypeId])
            ->count();

        if ($vacationCount >= 2) {
            return true;
        }

        // Check for regular clock in/out pair
        return Attendance::where('employee_id', $employeeId)
            ->whereDate('punch_time', $date)
            ->whereIn('status', ['Complete', 'Migrated'])
            ->whereIn('punch_type_id', [$this->clockInTypeId, $this->clockOutTypeId])
            ->count() >= 2;
    }

    /**
     * Check if all attendance records within the pay period are properly migrated.
     */
    public function checkAndUpdatePayPeriodProcessedStatus(PayPeriod $payPeriod): void
    {
        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        // Use count queries instead of loading all records
        $totalRecords = Attendance::whereBetween('punch_time', [$startDate, $endDate])->count();

        if ($totalRecords === 0) {
            Log::info("PunchMigrationService: No attendance records found for PayPeriod ID: {$payPeriod->id}");

            return;
        }

        $migratedCount = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->whereNotNull('punch_type_id')
            ->where('status', 'Migrated')
            ->count();

        Log::info("PunchMigrationService: PayPeriod ID {$payPeriod->id}: {$migratedCount}/{$totalRecords} migrated");

        if ($migratedCount === $totalRecords) {
            $payPeriod->update(['is_processed' => true]);
            Log::info("PunchMigrationService: PayPeriod ID {$payPeriod->id} marked as processed");
        }
    }
}
