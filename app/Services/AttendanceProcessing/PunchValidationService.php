<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\DB;

class PunchValidationService
{
    /**
     * Validates punches and migrates completed records.
     */
    public function validateAndProcessCompletedRecords(array $attendanceIds): void
    {
        if (empty($attendanceIds)) {
            return;
        }

        $this->validatePunches($attendanceIds);

        app(PunchMigrationService::class)->migrateCompletedPunches();
    }

    public function processCompletedAttendanceRecords(array $attendanceIds): void
    {
        app(PunchValidationService::class)->validateAndProcessCompletedRecords($attendanceIds);
    }

    public function validatePunchesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        // Use cursor() to iterate one record at a time
        foreach (Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'Valid')
            ->where('is_migrated', false)
            ->cursor() as $attendance) {
            // Skip records missing required fields
            if (! $attendance->punch_time || ! $attendance->employee_id || ! $attendance->punch_state) {
                continue;
            }

            // Skip records with invalid punch_state
            if (! in_array($attendance->punch_state, ['start', 'stop'])) {
                continue;
            }
        }
    }

    /**
     * Resolves overlapping records for employees with possible extra attendance records on the same day.
     */
    public function resolveOverlappingRecords(PayPeriod $payPeriod): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        // Use cursor for the aggregation query results
        $overlappingRecords = Attendance::select('employee_id', 'shift_date', DB::raw('COUNT(*) as record_count'))
            ->whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->groupBy('employee_id', 'shift_date')
            ->having('record_count', '>', 1)
            ->cursor();

        foreach ($overlappingRecords as $record) {
            $employeeId = $record->employee_id;
            $punchDate = $record->shift_date;

            // Use count queries instead of loading all records
            $startPunches = Attendance::where('employee_id', $employeeId)
                ->where('shift_date', $punchDate)
                ->where('punch_state', 'start')
                ->count();

            $stopPunches = Attendance::where('employee_id', $employeeId)
                ->where('shift_date', $punchDate)
                ->where('punch_state', 'stop')
                ->count();

            if ($startPunches !== $stopPunches) {
                continue;
            }
        }
    }
}
