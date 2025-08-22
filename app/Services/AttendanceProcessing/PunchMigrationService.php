<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\Punch;
use App\Services\RoundingRuleService;
use App\Services\TimeGrouping\AttendanceTimeGroupService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
        Log::info("PunchMigrationService ✅ Starting migratePunchesWithinPayPeriod for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        // ✅ Fetch completed attendance records (including Holidays)
        $attendances = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->where('status', 'Complete')
            ->get();

        Log::info("PunchMigrationService ✅ Found {$attendances->count()} completed attendance records for PayPeriod ID: {$payPeriod->id}");

        foreach ($attendances as $attendance) {
            try {
                Log::info("PunchMigrationService ✅ Processing Attendance ID: {$attendance->id} for Employee ID: {$attendance->employee_id}");

                // ✅ Identify Holiday Attendance
                $isHolidayRecord = !is_null($attendance->holiday_id);

                $roundGroupId = $attendance->employee->round_group_id ?? null;
                $roundedPunchTime = $roundGroupId
                    ? $this->roundingRuleService->getRoundedTime(new \DateTime($attendance->punch_time), $roundGroupId)
                    : new \DateTime($attendance->punch_time);

                Log::info("PunchMigrationService ✅ Rounded punch time for Attendance ID {$attendance->id}: {$roundedPunchTime->format('Y-m-d H:i:s')}");

                $externalGroupId = $attendance->external_group_id;

                Log::info("PunchMigrationService ✅ Calling getOrCreateGroupAndAssign for Attendance ID: {$attendance->id}");
                Log::debug("PunchMigrationService 🧪 DEBUG: Checking AttendanceTimeGroupService execution path for Attendance ID: {$attendance->id}");
                Log::info("PunchMigrationService ✅ [Step 11] About to execute AttendanceTimeGroupService::getOrCreateGroupAndAssign for Attendance ID: {$attendance->id}");
                app(AttendanceTimeGroupService::class)->getOrCreateGroupAndAssign($attendance, auth()->id() ?? 0);
                Log::info("PunchMigrationService ✅ [Step 11] Completed AttendanceTimeGroupService::getOrCreateGroupAndAssign for Attendance ID: {$attendance->id}");
                Log::debug("PunchMigrationService 🧪 DEBUG: Post-Assignment Check - shift_date: {$attendance->shift_date}, external_group_id: {$attendance->external_group_id}");

                if (empty($attendance->shift_date)) {
                    Log::warning("PunchMigrationService ⚠️ Still missing shift_date after group assignment for Attendance ID {$attendance->id}");
                    continue;
                }

                if (!$isHolidayRecord && !$this->hasStartAndStopTime($attendance->employee_id, $attendance->punch_time)) {
                    Log::warning("PunchMigrationService ⚠️ Skipping migration for Attendance ID {$attendance->id} due to missing Clock In or Clock Out.");
                    continue;
                }

                // ✅ Begin Database Transaction
                DB::beginTransaction();

                $punchData = [
                    'employee_id'       => $attendance->employee_id,
                    'device_id'         => $attendance->device_id,
                    'punch_type_id'     => $attendance->punch_type_id,
                    'punch_state'       => $attendance->punch_state, // ✅ Added punch_state
                    'punch_time'        => $roundedPunchTime->format('Y-m-d H:i:s'),
                    'is_altered'        => true,
                    'pay_period_id'     => $payPeriod->id,
                    'attendance_id'     => $attendance->id,
                    'external_group_id' => $externalGroupId,
                    'shift_date'        => $attendance->shift_date,
                    'holiday_id'        => $attendance->holiday_id,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];

                Log::info("PunchMigrationService ✅ Inserting punch record: " . json_encode($punchData));

                $punch = Punch::create($punchData);

                if ($punch) {
                    Log::info("PunchMigrationService ✅ Punch record created successfully: Punch ID {$punch->id} for Attendance ID {$attendance->id}");
                } else {
                    Log::error("PunchMigrationService ❌ Punch insert failed for Attendance ID {$attendance->id}");
                    DB::rollBack();
                    continue;
                }

                $attendance->update([
                    'status' => 'Migrated',
                    'is_migrated' => true,
                ]);

                Log::info("PunchMigrationService ✅ Updated Attendance ID {$attendance->id} status to 'Migrated'");

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("PunchMigrationService ❌ Error in migratePunchesWithinPayPeriod for Attendance ID {$attendance->id}: " . $e->getMessage());
            }
        }

        Log::info("PunchMigrationService ✅ Completed migratePunchesWithinPayPeriod for PayPeriod ID: {$payPeriod->id}");
    }

    private function getPunchTypeId(string $type): ?int
    {
        return DB::table('punch_types')->where('name', $type)->value('id');
    }

    /**
     * Ensure at least one Clock In and one Clock Out punch exists per day before migration.
     */
    private function hasStartAndStopTime(int $employeeId, string $punchTime): bool
    {
        $date = Carbon::parse($punchTime)->toDateString();

        $holidayRecords = Attendance::where('employee_id', $employeeId)
            ->whereDate('punch_time', $date)
            ->whereNotNull('holiday_id')
            ->get();

        return $holidayRecords->isNotEmpty() || Attendance::where('employee_id', $employeeId)
                ->whereDate('punch_time', $date)
                ->whereIn('punch_type_id', [$this->getPunchTypeId('Clock In'), $this->getPunchTypeId('Clock Out')])
                ->count() >= 2;
    }
}
