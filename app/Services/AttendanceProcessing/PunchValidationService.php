<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;

class PunchValidationService
{
    /**
     * Validates and prepares punches for migration within a pay period.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function validatePunchesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'Valid')
            ->where('is_migrated', false)
            ->get();

        $punches = $attendances->map(function ($attendance) use ($payPeriod) {
            return [
                'employee_id' => $attendance->employee_id,
                'device_id' => $attendance->device_id,
                'punch_type_id' => null, // Determine punch type logic here
                'punch_time' => $attendance->punch_time,
                'pay_period_id' => $payPeriod->id,
                'is_altered' => false,
                'attendance_id' => $attendance->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        // Insert punches into the punches table
        \DB::table('punches')->insert($punches);

        // Mark attendances as migrated
        $attendanceIds = $attendances->pluck('id')->toArray();
        Attendance::whereIn('id', $attendanceIds)->update(['is_migrated' => true]);
    }
}
