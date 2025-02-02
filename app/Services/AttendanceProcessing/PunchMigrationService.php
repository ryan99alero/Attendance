<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\Punch;
use App\Services\RoundingRuleService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
        Log::info("🔄 Starting punch migration for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        // If the pay period includes today, exclude the last day
        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        // Fetch completed attendances within the pay period
        $attendances = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->where('status', 'Complete')
            ->get();

        Log::info("📌 Found {$attendances->count()} completed attendance records for PayPeriod ID: {$payPeriod->id}");

        foreach ($attendances as $attendance) {
            try {
                Log::info("⏳ Processing Attendance ID: {$attendance->id} for Employee ID: {$attendance->employee_id}");

                // Determine rounding group
                $roundGroupId = $attendance->employee->round_group_id ?? null;
                $roundedPunchTime = $roundGroupId
                    ? $this->roundingRuleService->getRoundedTime(new \DateTime($attendance->punch_time), $roundGroupId)
                    : new \DateTime($attendance->punch_time);

                Log::info("🕒 Rounded punch time for Attendance ID {$attendance->id}: {$roundedPunchTime->format('Y-m-d H:i:s')}");

                // Ensure at least one Clock In and Clock Out punch exists per day before migration
                if (!$this->hasStartAndStopTime($attendance->employee_id, $attendance->punch_time)) {
                    Log::warning("⚠️ Skipping migration for Attendance ID {$attendance->id} due to missing Clock In or Clock Out.");
                    continue;
                }

                // Create Punch record
                Punch::create([
                    'employee_id' => $attendance->employee_id,
                    'device_id' => $attendance->device_id,
                    'punch_type_id' => $attendance->punch_type_id,
                    'punch_time' => $roundedPunchTime->format('Y-m-d H:i:s'),
                    'is_altered' => true,
                    'pay_period_id' => $payPeriod->id,
                    'attendance_id' => $attendance->id,
                ]);

                Log::info("✅ Punch record created for Attendance ID {$attendance->id}");

                // Mark attendance as migrated
                $attendance->update([
                    'status' => 'Migrated',
                    'is_migrated' => true,
                ]);

            } catch (\Exception $e) {
                Log::error("❌ Error migrating Attendance ID {$attendance->id}: " . $e->getMessage());
            }
        }

        Log::info("✅ Completed punch migration for PayPeriod ID: {$payPeriod->id}");
    }

    /**
     * Ensure there is at least one Clock In and one Clock Out punch for a given employee and date.
     *
     * @param int $employeeId
     * @param string $punchTime
     * @return bool
     */
    private function hasStartAndStopTime(int $employeeId, string $punchTime): bool
    {
        $date = Carbon::parse($punchTime)->toDateString();

        return Attendance::where('employee_id', $employeeId)
                ->whereDate('punch_time', $date)
                ->whereIn('punch_type_id', [
                    $this->getPunchTypeId('Clock In'),
                    $this->getPunchTypeId('Clock Out'),
                ])
                ->count() >= 2;
    }

    /**
     * Retrieve the punch type ID by name.
     *
     * @param string $type
     * @return int|null
     */
    private function getPunchTypeId(string $type): ?int
    {
        return \DB::table('punch_types')->where('name', $type)->value('id');
    }
}
