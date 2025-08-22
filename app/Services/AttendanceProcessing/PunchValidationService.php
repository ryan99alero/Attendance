<?php

namespace App\Services\AttendanceProcessing;

use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use Illuminate\Support\Facades\Log;
use App\Models\PayPeriod;

class PunchValidationService
{
    protected const OVERLAPPING_ENTRY_METHODS = ['vacation', 'holiday'];

    /**
     * Validates punches within a pay period.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function validateAndProcessCompletedRecords(array $attendanceIds): void
    {
        Log::info("[PunchValidationService] Validating and processing completed records: " . json_encode($attendanceIds));

        if (empty($attendanceIds)) {
            Log::warning("[PunchValidationService] No attendance records provided for validation and processing.");
            return;
        }

        // Step 1: Validate punches before processing
        $this->validatePunches($attendanceIds);

        // Step 2: Migrate valid records to the punches table
        app(PunchMigrationService::class)->migrateCompletedPunches();

        Log::info("[PunchValidationService] Attendance records successfully validated and migrated.");
    }

    public function processCompletedAttendanceRecords(array $attendanceIds): void
    {
        Log::info("[PunchValidationService] Processing completed attendance records.");

        app(PunchValidationService::class)->validateAndProcessCompletedRecords($attendanceIds);
    }

    public function validatePunchesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'Valid')
            ->where('is_migrated', false)
            ->get();

        foreach ($attendances as $attendance) {
            // Ensure punch time, employee ID, and punch_state are valid
            if (!$attendance->punch_time || !$attendance->employee_id || !$attendance->punch_state) {
                Log::warning("[PunchValidationService] Invalid attendance record - Missing fields. ID: {$attendance->id}");
                continue;
            }

            // Ensure punch_state is properly assigned (must be 'start' or 'stop')
            if (!in_array($attendance->punch_state, ['start', 'stop'])) {
                Log::warning("[PunchValidationService] Invalid punch_state for Attendance ID: {$attendance->id}. Expected 'start' or 'stop', found '{$attendance->punch_state}'");
                continue;
            }

            Log::info("[PunchValidationService] Validated attendance record ID: {$attendance->id} for Employee ID: {$attendance->employee_id}.");
        }

        Log::info("[PunchValidationService] Validation completed for PayPeriod ID: {$payPeriod->id}");
    }

    /**
     * Resolves overlapping records for employees with possible extra attendance records on the same day.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function resolveOverlappingRecords(PayPeriod $payPeriod): void
    {
        Log::info("[PunchValidationService] Resolving overlapping records for PayPeriod ID: {$payPeriod->id}");

        // Step 1: Identify overlapping records
        $overlappingRecords = Attendance::select('employee_id', 'shift_date', DB::raw('COUNT(*) as record_count'))
            ->whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->groupBy('employee_id', 'shift_date')
            ->having('record_count', '>', 1)
            ->get();

        foreach ($overlappingRecords as $record) {
            $employeeId = $record->employee_id;
            $punchDate = $record->shift_date;

            // Step 2: Retrieve all attendance records for the day
            $dailyRecords = Attendance::where('employee_id', $employeeId)
                ->where('shift_date', $punchDate)
                ->get();

            if ($dailyRecords->isEmpty()) {
                Log::warning("[PunchValidationService] No records found for Employee ID: {$employeeId} on {$punchDate}");
                continue;
            }

            Log::info("[PunchValidationService] Found {$dailyRecords->count()} attendance records for Employee ID: {$employeeId} on {$punchDate}");

            // Ensure an equal number of start/stop punches
            $startPunches = $dailyRecords->where('punch_state', 'start')->count();
            $stopPunches = $dailyRecords->where('punch_state', 'stop')->count();

            if ($startPunches !== $stopPunches) {
                Log::warning("[PunchValidationService] Imbalanced start/stop punches for Employee ID: {$employeeId} on {$punchDate}. Start: {$startPunches}, Stop: {$stopPunches}");
                continue;
            }

            Log::info("[PunchValidationService] Resolved overlapping records for Employee ID: {$employeeId} on {$punchDate}");
        }

        Log::info("[PunchValidationService] Overlapping record resolution completed for PayPeriod ID: {$payPeriod->id}");
    }
}
