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
        Log::info("🔍 [PunchValidationService] Validating and processing completed records: " . json_encode($attendanceIds));

        if (empty($attendanceIds)) {
            Log::warning("⚠️ No attendance records provided for validation and processing.");
            return;
        }

        // Step 1: Validate punches before processing
        $this->validatePunches($attendanceIds);

        // Step 2: Migrate valid records to the punches table
        app(PunchMigrationService::class)->migrateCompletedPunches();

        Log::info("✅ [PunchValidationService] Attendance records successfully validated and migrated.");
    }
    public function processCompletedAttendanceRecords(array $attendanceIds): void
    {
        Log::info("🛠 [processCompletedAttendanceRecords] Calling PunchValidationService for processing.");

        app(PunchValidationService::class)->validateAndProcessCompletedRecords($attendanceIds);
    }
    public function validatePunchesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'Valid')
            ->where('is_migrated', false)
            ->get();

        foreach ($attendances as $attendance) {
            // Example of validation logic
            if (!$attendance->punch_time || !$attendance->employee_id) {
                \Log::warning("Invalid attendance record ID: {$attendance->id}");
                continue;
            }

            \Log::info("Validated attendance record ID: {$attendance->id} for Employee ID: {$attendance->employee_id}.");
        }

        \Log::info("Validation completed for punches in PayPeriod ID: {$payPeriod->id}");
    }

    /**
     * Resolves overlapping records for employees with possible extra attendance records on the same day.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function resolveOverlappingRecords(PayPeriod $payPeriod): void
    {
        \Log::info("resolveOverlappingRecords called for PayPeriod ID: {$payPeriod->id}");

        // Step 1: Identify overlapping records
        $overlappingRecords = Attendance::select('employee_id', DB::raw('DATE(punch_time) as punch_date'), DB::raw('COUNT(*) as record_count'))
            ->whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->groupBy('employee_id', DB::raw('DATE(punch_time)'))
            ->having('record_count', '>', 1)
            ->get();

        foreach ($overlappingRecords as $record) {
            $employeeId = $record->employee_id;
            $punchDate = $record->punch_date;

           // \Log::info("Checking potential extra records for Employee ID: {$employeeId} on {$punchDate}");

            // Step 2: Retrieve all attendance records for the day
            $dailyRecords = Attendance::where('employee_id', $employeeId)
                ->whereDate('punch_time', $punchDate)
                ->get();

            if ($dailyRecords->isEmpty()) {
                \Log::warning("No records found for Employee ID: {$employeeId} on {$punchDate}");
                continue;
            }

          //  \Log::info("Found {$dailyRecords->count()} attendance records for Employee ID: {$employeeId} on {$punchDate}");

            // Placeholder for future logic to handle extra records
            // Example: Validate or prioritize certain records based on business rules
        }

        \Log::info("resolveOverlappingRecords completed for PayPeriod ID: {$payPeriod->id}");
    }
}
