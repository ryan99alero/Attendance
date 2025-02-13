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

    public function __construct(RoundingRuleService $roundingRuleService)
    {
        $this->roundingRuleService = $roundingRuleService;
    }

    /**
     * Migrate punches to the Punches table and mark as migrated.
     */
    public function migratePunchesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("🔄 Starting punch migration for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        // ✅ Fetch completed attendance records (include Holidays)
        $attendances = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->where('status', 'Complete')
            ->get();

        Log::info("📌 Found {$attendances->count()} completed attendance records for PayPeriod ID: {$payPeriod->id}");

        foreach ($attendances as $attendance) {
            try {
                Log::info("⏳ Processing Attendance ID: {$attendance->id} for Employee ID: {$attendance->employee_id}");

                // ✅ Identify Holiday Attendance
                $isHolidayRecord = !is_null($attendance->holiday_id);
                if ($isHolidayRecord) {
                    Log::info("🎉 Attendance ID: {$attendance->id} is a Holiday record.");
                }

                $roundGroupId = $attendance->employee->round_group_id ?? null;
                $roundedPunchTime = $roundGroupId
                    ? $this->roundingRuleService->getRoundedTime(new \DateTime($attendance->punch_time), $roundGroupId)
                    : new \DateTime($attendance->punch_time);

                Log::info("🕒 Rounded punch time for Attendance ID {$attendance->id}: {$roundedPunchTime->format('Y-m-d H:i:s')}");

                // ✅ Skip migration if Clock In or Clock Out is missing (except for Holidays)
                if (!$isHolidayRecord && !$this->hasStartAndStopTime($attendance->employee_id, $attendance->punch_time)) {
                    Log::warning("⚠️ Skipping migration for Attendance ID {$attendance->id} due to missing Clock In or Clock Out.");
                    continue;
                }

                // ✅ Ensure external_group_id and shift_date are available
                if (empty($attendance->external_group_id) || empty($attendance->shift_date)) {
                    Log::warning("⚠️ Skipping Attendance ID {$attendance->id} due to missing external_group_id or shift_date.");
                    continue;
                }

                // ✅ Create Punch record with `external_group_id` & `shift_date`
                Punch::create([
                    'employee_id'       => $attendance->employee_id,
                    'device_id'         => $attendance->device_id,
                    'punch_type_id'     => $attendance->punch_type_id,
                    'punch_time'        => $roundedPunchTime->format('Y-m-d H:i:s'),
                    'is_altered'        => true,
                    'pay_period_id'     => $payPeriod->id,
                    'attendance_id'     => $attendance->id,
                    'external_group_id' => $attendance->external_group_id,
                    'shift_date'        => $attendance->shift_date,
                    'holiday_id'        => $attendance->holiday_id, // ✅ Ensure holiday data is retained
                ]);

                Log::info("✅ Punch record created for Attendance ID {$attendance->id}");

                // ✅ Mark attendance as migrated
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
     * Ensure at least one Clock In and one Clock Out punch exists per day before migration (excluding Holidays).
     */
    private function hasStartAndStopTime(int $employeeId, string $punchTime): bool
    {
        $date = Carbon::parse($punchTime)->toDateString();

        // ✅ If the record is a Holiday, skip this check
        $isHolidayRecord = Attendance::where('employee_id', $employeeId)
            ->whereDate('punch_time', $date)
            ->whereNotNull('holiday_id')
            ->exists();

        if ($isHolidayRecord) {
            Log::info("🎉 Skipping Clock In/Out check for Holiday Attendance on {$date}");
            return true;
        }

        // ✅ Regular attendance requires both Clock In & Clock Out
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
     */
    private function getPunchTypeId(string $type): ?int
    {
        return \DB::table('punch_types')->where('name', $type)->value('id');
    }
}
