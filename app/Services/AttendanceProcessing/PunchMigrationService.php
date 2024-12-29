<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\Punch;
use App\Services\RoundingRuleService;

class PunchMigrationService
{
    protected RoundingRuleService $roundingRuleService;

    /**
     * Constructor to inject RoundingRuleService.
     *
     * @param RoundingRuleService $roundingRuleService
     */
    public function __construct(RoundingRuleService $roundingRuleService)
    {
        $this->roundingRuleService = $roundingRuleService;
    }

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
            try {
                // Fetch the round group ID from the employee
                $roundGroupId = $attendance->employee->round_group_id ?? null;

                if (!$roundGroupId) {
                    \Log::warning("No round group ID found for employee ID: {$attendance->employee_id}. Skipping rounding.");
                    $roundedPunchTime = new \DateTime($attendance->punch_time);
                } else {
                    // Get the rounded punch time using the RoundingRuleService
                    $roundedPunchTime = $this->roundingRuleService->getRoundedTime(
                        new \DateTime($attendance->punch_time),
                        $roundGroupId
                    );
                }

                // Create Punch record with the rounded time
                Punch::create([
                    'employee_id' => $attendance->employee_id,
                    'device_id' => $attendance->device_id,
                    'punch_type_id' => $attendance->punch_type_id,
                    'punch_time' => $roundedPunchTime->format('Y-m-d H:i:s'),
                    'is_altered' => true, // Mark as altered because of rounding
                    'pay_period_id' => $payPeriod->id, // Assign pay_period_id
                    'attendance_id' => $attendance->id, // Link to the Attendance record
                ]);

                // Mark attendance as migrated
                $attendance->status = 'Migrated';
                $attendance->save();

                \Log::info("Attendance ID {$attendance->id} migrated with rounded time {$roundedPunchTime->format('Y-m-d H:i:s')}.");
            } catch (\Exception $e) {
                \Log::error("Error migrating Attendance ID {$attendance->id}: " . $e->getMessage());
            }
        }
    }
}
