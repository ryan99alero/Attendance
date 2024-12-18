<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\Punch;

class PunchMigrationService
{
    /**
     * Migrate punches to the Punches table and mark as migrated.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function migratePunchesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        // Fetch completed attendances within the pay period
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'Complete')
            ->get();

        foreach ($attendances as $attendance) {
            // Create Punch record with required fields
            Punch::create([
                'employee_id' => $attendance->employee_id,
                'device_id' => $attendance->device_id,
                'punch_type_id' => $attendance->punch_type_id,
                'punch_time' => $attendance->punch_time,
                'is_altered' => false,
                'pay_period_id' => $payPeriod->id, // Assign pay_period_id
                'attendance_id' => $attendance->id, // Link to the Attendance record
            ]);

            // Mark attendance as migrated
            $attendance->status = 'Migrated';
            $attendance->save();
        }
    }
}
