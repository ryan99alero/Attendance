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
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'Complete')
            ->get();

        foreach ($attendances as $attendance) {
            Punch::create([
                'employee_id' => $attendance->employee_id,
                'device_id' => $attendance->device_id,
                'punch_type_id' => $attendance->punch_type_id,
                'punch_time' => $attendance->punch_time,
                'is_altered' => false,
            ]);

            $attendance->status = 'Migrated';
            $attendance->save();
        }
    }
}
