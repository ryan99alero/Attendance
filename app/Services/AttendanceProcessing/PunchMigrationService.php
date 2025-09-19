<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\Punch;
use App\Services\RoundingRuleService;
use App\Services\TimeGrouping\AttendanceTimeGroupService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PunchMigrationService
{
    protected RoundingRuleService $roundingRuleService;

    public function __construct(RoundingRuleService $roundingRuleService)
    {
        $this->roundingRuleService = $roundingRuleService;
    }

    /**
     * Migrate punches to the Punches table and mark as migrated.
     */
    public function migratePunchesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("PunchMigrationService ✅ Starting migratePunchesWithinPayPeriod for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        // ✅ Fetch completed attendance records (including Holidays)
        $attendances = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->where('status', 'Complete')
            ->get();

        Log::info("PunchMigrationService ✅ Found {$attendances->count()} completed attendance records for PayPeriod ID: {$payPeriod->id}");

        foreach ($attendances as $attendance) {
            try {
                Log::info("PunchMigrationService ✅ Processing Attendance ID: {$attendance->id} for Employee ID: {$attendance->employee_id}");

                // ✅ Identify Holiday and Vacation Attendance
                $isHolidayRecord = !is_null($attendance->holiday_id);
                $vacationClassificationId = DB::table('classifications')->where('code', 'VACATION')->value('id');
                $isVacationRecord = $attendance->classification_id === $vacationClassificationId;

                $roundGroupId = $attendance->employee->round_group_id ?? null;
                $roundedPunchTime = $roundGroupId
                    ? $this->roundingRuleService->getRoundedTime(new \DateTime($attendance->punch_time), $roundGroupId)
                    : new \DateTime($attendance->punch_time);

                Log::info("PunchMigrationService ✅ Rounded punch time for Attendance ID {$attendance->id}: {$roundedPunchTime->format('Y-m-d H:i:s')}");

                $externalGroupId = $attendance->external_group_id;

                Log::info("PunchMigrationService ✅ Calling getOrCreateGroupAndAssign for Attendance ID: {$attendance->id}");
                Log::debug("PunchMigrationService 🧪 DEBUG: Checking AttendanceTimeGroupService execution path for Attendance ID: {$attendance->id}");
                Log::info("PunchMigrationService ✅ [Step 11] About to execute AttendanceTimeGroupService::getOrCreateGroupAndAssign for Attendance ID: {$attendance->id}");
                app(AttendanceTimeGroupService::class)->getOrCreateGroupAndAssign($attendance, auth()->id() ?? 0);
                Log::info("PunchMigrationService ✅ [Step 11] Completed AttendanceTimeGroupService::getOrCreateGroupAndAssign for Attendance ID: {$attendance->id}");
                Log::debug("PunchMigrationService 🧪 DEBUG: Post-Assignment Check - shift_date: {$attendance->shift_date}, external_group_id: {$attendance->external_group_id}");

                if (empty($attendance->shift_date)) {
                    Log::warning("PunchMigrationService ⚠️ Still missing shift_date after group assignment for Attendance ID {$attendance->id}");
                    continue;
                }

                if (!$isHolidayRecord && !$isVacationRecord && !$this->hasStartAndStopTime($attendance->employee_id, $attendance->punch_time)) {
                    Log::warning("PunchMigrationService ⚠️ Skipping migration for Attendance ID {$attendance->id} due to missing Clock In or Clock Out.");
                    continue;
                }

                // Skip individual unpaired punches (marked as NeedsReview during processing)
                if ($attendance->status === 'NeedsReview' && str_contains($attendance->issue_notes ?? '', 'Unpaired punch')) {
                    Log::warning("PunchMigrationService ⚠️ Skipping migration for Attendance ID {$attendance->id} - marked as unpaired punch.");
                    continue;
                }

                // ✅ Begin Database Transaction
                DB::beginTransaction();

                // Ensure proper punch_type_id assignment, especially for holiday records
                $punchTypeId = $attendance->punch_type_id;
                if (is_null($punchTypeId)) {
                    // Fallback to Unknown punch type if punch_type_id is missing
                    $punchTypeId = $this->getPunchTypeId('Unknown');
                    Log::warning("PunchMigrationService ⚠️ Missing punch_type_id for Attendance ID {$attendance->id}, using Unknown punch type");
                }

                // Ensure proper classification for holiday and vacation records
                $classificationId = $attendance->classification_id;
                if ($isHolidayRecord && is_null($classificationId)) {
                    $classificationId = $this->getClassificationId('Holiday');
                    Log::info("PunchMigrationService ✅ Setting Holiday classification for Attendance ID {$attendance->id}");
                } elseif ($isVacationRecord && is_null($classificationId)) {
                    $classificationId = $vacationClassificationId;
                    Log::info("PunchMigrationService ✅ Setting Vacation classification for Attendance ID {$attendance->id}");
                }

                $punchData = [
                    'employee_id'       => $attendance->employee_id,
                    'device_id'         => $attendance->device_id,
                    'punch_type_id'     => $punchTypeId,
                    'punch_state'       => $attendance->punch_state,
                    'punch_time'        => $roundedPunchTime->format('Y-m-d H:i:s'),
                    'is_altered'        => true,
                    'pay_period_id'     => $payPeriod->id,
                    'attendance_id'     => $attendance->id,
                    'external_group_id' => $externalGroupId,
                    'shift_date'        => $attendance->shift_date,
                    'classification_id' => $classificationId,
                ];

                Log::info("PunchMigrationService ✅ Inserting punch record: " . json_encode($punchData));

                $punch = Punch::create($punchData);

                if ($punch) {
                    Log::info("PunchMigrationService ✅ Punch record created successfully: Punch ID {$punch->id} for Attendance ID {$attendance->id}");
                } else {
                    Log::error("PunchMigrationService ❌ Punch insert failed for Attendance ID {$attendance->id}");
                    DB::rollBack();
                    continue;
                }

                $attendance->update([
                    'status' => 'Migrated',
                    'is_migrated' => true,
                ]);

                Log::info("PunchMigrationService ✅ Updated Attendance ID {$attendance->id} status to 'Migrated'");

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("PunchMigrationService ❌ Error in migratePunchesWithinPayPeriod for Attendance ID {$attendance->id}: " . $e->getMessage());
            }
        }

        Log::info("PunchMigrationService ✅ Completed migratePunchesWithinPayPeriod for PayPeriod ID: {$payPeriod->id}");

        // ✅ Check if all records are properly migrated and update PayPeriod if needed
        $this->checkAndUpdatePayPeriodProcessedStatus($payPeriod);
    }

    private function getPunchTypeId(string $type): ?int
    {
        return DB::table('punch_types')->where('name', $type)->value('id');
    }

    private function getClassificationId(string $classification): ?int
    {
        return DB::table('classifications')->where('name', $classification)->value('id');
    }

    /**
     * Ensure at least one Clock In and one Clock Out punch exists per day before migration.
     */
    private function hasStartAndStopTime(int $employeeId, string $punchTime): bool
    {
        $date = Carbon::parse($punchTime)->toDateString();

        // Check for holiday records
        $holidayRecords = Attendance::where('employee_id', $employeeId)
            ->whereDate('punch_time', $date)
            ->whereNotNull('holiday_id')
            ->whereIn('status', ['Complete', 'Migrated'])
            ->get();

        // Check for vacation records (classification_id = 2 for VACATION)
        $vacationClassificationId = DB::table('classifications')->where('code', 'VACATION')->value('id');
        $vacationRecords = Attendance::where('employee_id', $employeeId)
            ->whereDate('punch_time', $date)
            ->where('classification_id', $vacationClassificationId)
            ->whereIn('status', ['Complete', 'Migrated'])
            ->whereIn('punch_type_id', [$this->getPunchTypeId('Clock In'), $this->getPunchTypeId('Clock Out')])
            ->get();

        // Return true if we have holiday records, complete vacation pair, or regular clock in/out pair
        return $holidayRecords->isNotEmpty()
            || $vacationRecords->count() >= 2
            || Attendance::where('employee_id', $employeeId)
                ->whereDate('punch_time', $date)
                ->whereIn('status', ['Complete', 'Migrated'])
                ->whereIn('punch_type_id', [$this->getPunchTypeId('Clock In'), $this->getPunchTypeId('Clock Out')])
                ->count() >= 2;
    }

    /**
     * Check if all attendance records within the pay period are properly migrated
     * and update PayPeriod.is_processed = true if conditions are met.
     */
    public function checkAndUpdatePayPeriodProcessedStatus(PayPeriod $payPeriod): void
    {
        Log::info("PunchMigrationService ✅ Checking PayPeriod processed status for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        // Get all attendance records within the pay period
        $allRecords = Attendance::whereBetween('punch_time', [$startDate, $endDate])->get();
        $totalRecords = $allRecords->count();

        if ($totalRecords === 0) {
            Log::info("PunchMigrationService ℹ️ No attendance records found for PayPeriod ID: {$payPeriod->id}");
            return;
        }

        // Check if all records have punch_type_id and status = 'Migrated'
        $properlyMigratedRecords = $allRecords->filter(function ($record) {
            return !is_null($record->punch_type_id) && $record->status === 'Migrated';
        });

        $migratedCount = $properlyMigratedRecords->count();

        Log::info("PunchMigrationService ✅ Migration Status for PayPeriod ID {$payPeriod->id}: {$migratedCount}/{$totalRecords} records properly migrated");

        // If all records are properly migrated, set PayPeriod.is_processed = true
        if ($migratedCount === $totalRecords) {
            $payPeriod->update(['is_processed' => true]);
            Log::info("PunchMigrationService ✅ Set PayPeriod ID {$payPeriod->id} is_processed = true - All {$totalRecords} records are properly migrated");
        } else {
            $unmigratedRecords = $totalRecords - $migratedCount;
            Log::info("PunchMigrationService ⚠️ PayPeriod ID {$payPeriod->id} NOT marked as processed - {$unmigratedRecords} records still need migration");

            // Optional: Log details about unmigrated records for debugging
            $unmigrated = $allRecords->filter(function ($record) {
                return is_null($record->punch_type_id) || $record->status !== 'Migrated';
            });

            foreach ($unmigrated as $record) {
                Log::debug("PunchMigrationService 🔍 Unmigrated record - Attendance ID: {$record->id}, Status: {$record->status}, PunchType: " . ($record->punch_type_id ?? 'NULL'));
            }
        }
    }

}
