<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\Punch;
use App\Services\RoundingRuleService;
use Illuminate\Support\Facades\Log;

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
        Log::info("Starting punch migration for PayPeriod ID: {$payPeriod->id}");

        // Fetch completed attendances within the pay period explicitly tied to the current pay period
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'Complete')
            ->get();

        Log::info("Found {$attendances->count()} completed attendance records for PayPeriod ID: {$payPeriod->id}");

        foreach ($attendances as $attendance) {
            try {
                Log::info("Processing Attendance ID: {$attendance->id} for Employee ID: {$attendance->employee_id}");

                // Fetch the round group ID from the employee
                $roundGroupId = $attendance->employee->round_group_id ?? null;

                if (!$roundGroupId) {
                    Log::warning("No round group ID found for Employee ID: {$attendance->employee_id}. Skipping rounding.");
                    $roundedPunchTime = new \DateTime($attendance->punch_time);
                } else {
                    // Get the rounded punch time using the RoundingRuleService
                    $roundedPunchTime = $this->roundingRuleService->getRoundedTime(
                        new \DateTime($attendance->punch_time),
                        $roundGroupId
                    );
                    Log::info("Rounded punch time for Attendance ID {$attendance->id}: {$roundedPunchTime->format('Y-m-d H:i:s')}");
                }

                // Log punch creation details
                Log::info("Creating punch record for Attendance ID {$attendance->id}");

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

                Log::info("Punch record created for Attendance ID {$attendance->id}");

                // Mark attendance as migrated
                $attendance->status = 'Migrated';
                $attendance->is_migrated = true; // Ensure the migrated flag is set
                $attendance->save();

                Log::info("Attendance ID {$attendance->id} marked as Migrated.");
            } catch (\Exception $e) {
                Log::error("Error migrating Attendance ID {$attendance->id}: " . $e->getMessage());
            }
        }

        Log::info("Completed punch migration for PayPeriod ID: {$payPeriod->id}");
    }
}
