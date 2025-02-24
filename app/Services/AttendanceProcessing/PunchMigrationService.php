<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\Punch;
use App\Services\RoundingRuleService;
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
        Log::info("PM1 🔄 Starting punch migration for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        // ✅ Fetch completed attendance records (including Holidays)
        $attendances = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->where('status', 'Complete')
            ->get();

        Log::info("PM2 📌 Found {$attendances->count()} completed attendance records for PayPeriod ID: {$payPeriod->id}");

        foreach ($attendances as $attendance) {
            try {
                Log::info("PM3 ⏳ Processing Attendance ID: {$attendance->id} for Employee ID: {$attendance->employee_id}");

                // ✅ Identify Holiday Attendance
                $isHolidayRecord = !is_null($attendance->holiday_id);

                $roundGroupId = $attendance->employee->round_group_id ?? null;
                $roundedPunchTime = $roundGroupId
                    ? $this->roundingRuleService->getRoundedTime(new \DateTime($attendance->punch_time), $roundGroupId)
                    : new \DateTime($attendance->punch_time);

                Log::info("PM5 🕒 Rounded punch time for Attendance ID {$attendance->id}: {$roundedPunchTime->format('Y-m-d H:i:s')}");

                if (empty($attendance->shift_date)) {
                    Log::warning("PM6 ⚠️ Skipping Attendance ID {$attendance->id} due to missing shift_date.");
                    continue;
                }

                $externalGroupId = $attendance->external_group_id;
                if ($isHolidayRecord && empty($externalGroupId)) {
                    $externalGroupId = "H-{$attendance->employee_id}-{$attendance->shift_date}";

                    // ✅ Ensure external_group_id length is within allowed limit
                    if (strlen($externalGroupId) > 40) {
                        Log::error("PM7.2 ❌ external_group_id '{$externalGroupId}' is too long. Skipping Attendance ID: {$attendance->id}");
                        continue;
                    }

                    $attendance->update(['external_group_id' => $externalGroupId]);
                }

                $groupExists = DB::table('attendance_time_groups')
                    ->where('external_group_id', $externalGroupId)
                    ->exists();

                if (!$groupExists) {
                    DB::table('attendance_time_groups')->insert([
                        'employee_id'         => $attendance->employee_id,
                        'external_group_id'   => $externalGroupId,
                        'shift_date'          => $attendance->shift_date,
                        'shift_window_start'  => $attendance->punch_time,
                        'shift_window_end'    => Carbon::parse($attendance->punch_time)->addHours(8),
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }

                if (!$isHolidayRecord && !$this->hasStartAndStopTime($attendance->employee_id, $attendance->punch_time)) {
                    Log::warning("PM9 ⚠️ Skipping migration for Attendance ID {$attendance->id} due to missing Clock In or Clock Out.");
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

                Log::info("PM10 🔄 Inserting punch record: " . json_encode($punchData));

                $punch = Punch::create($punchData);

                if ($punch) {
                    Log::info("PM11 ✅ Punch record created successfully: Punch ID {$punch->id} for Attendance ID {$attendance->id}");
                } else {
                    Log::error("PM12 ❌ Punch insert failed for Attendance ID {$attendance->id}");
                    DB::rollBack();
                    continue;
                }

                $attendance->update([
                    'status' => 'Migrated',
                    'is_migrated' => true,
                ]);

                Log::info("PM13 ✅ Updated Attendance ID {$attendance->id} status to 'Migrated'");

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("PM14 ❌ Error migrating Attendance ID {$attendance->id}: " . $e->getMessage());
            }
        }

        Log::info("PM15 ✅ Completed punch migration for PayPeriod ID: {$payPeriod->id}");
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
