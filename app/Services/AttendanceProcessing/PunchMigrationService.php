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
                if ($isHolidayRecord) {
                    Log::info("PM4 🎉 Attendance ID: {$attendance->id} is a Holiday record.");
                }

                $roundGroupId = $attendance->employee->round_group_id ?? null;
                $roundedPunchTime = $roundGroupId
                    ? $this->roundingRuleService->getRoundedTime(new \DateTime($attendance->punch_time), $roundGroupId)
                    : new \DateTime($attendance->punch_time);

                Log::info("PM5 🕒 Rounded punch time for Attendance ID {$attendance->id}: {$roundedPunchTime->format('Y-m-d H:i:s')}");

                // ✅ Ensure shift_date is available
                if (empty($attendance->shift_date)) {
                    Log::warning("PM6 ⚠️ Skipping Attendance ID {$attendance->id} due to missing shift_date.");
                    continue;
                }

                // ✅ Handle missing external_group_id for Holidays
                $externalGroupId = $attendance->external_group_id;

                if ($isHolidayRecord && empty($externalGroupId)) {
                    Log::info("PM7 🏷️ Assigning default external_group_id for Holiday Attendance ID: {$attendance->id}");

                    // ✅ Generate external_group_id with shorter prefix
                    $externalGroupId = "H-{$attendance->employee_id}-{$attendance->shift_date}";

                    $externalGroupIdLength = strlen($externalGroupId);
                    Log::info("PM7.1 Generated external_group_id: {$externalGroupId} ({$externalGroupIdLength} chars) for Attendance ID: {$attendance->id}");

                    // ✅ Ensure external_group_id length is within allowed limit
                    $maxLength = 40; // Ensure this matches the database column size
                    if ($externalGroupIdLength > $maxLength) {
                        Log::error("PM7.2 ❌ external_group_id '{$externalGroupId}' is too long ({$externalGroupIdLength} chars, max: {$maxLength}). Skipping Attendance ID: {$attendance->id}");
                        continue;
                    }

                    // ✅ Save external_group_id in Attendance
                    $attendance->update([
                        'external_group_id' => $externalGroupId,
                    ]);

                    Log::info("PM7.3 ✅ Updated Attendance ID {$attendance->id} with external_group_id: {$externalGroupId}");
                } else {
                    Log::info("PM7.4 ✅ Using existing external_group_id: {$externalGroupId} for Attendance ID: {$attendance->id}");
                }

                // ✅ Ensure attendance_time_groups has this external_group_id
                $groupExists = DB::table('attendance_time_groups')
                    ->where('external_group_id', $externalGroupId)
                    ->exists();

                if (!$groupExists) {
                    Log::info("PM8 🆕 Creating new entry in attendance_time_groups for external_group_id: {$externalGroupId}");

                    DB::table('attendance_time_groups')->insert([
                        'employee_id'         => $attendance->employee_id,
                        'external_group_id'   => $externalGroupId,
                        'shift_date'          => $attendance->shift_date,
                        'shift_window_start'  => $attendance->punch_time,
                        'shift_window_end'    => Carbon::parse($attendance->punch_time)->addHours(8), // Assuming an 8-hour shift
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);

                    Log::info("PM8.1 ✅ Inserted external_group_id: {$externalGroupId} into attendance_time_groups.");
                }

                // ✅ Skip migration if not a Holiday and missing Clock In/Out
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

                // ✅ Mark attendance as migrated
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
        $id = DB::table('punch_types')->where('name', $type)->value('id');

        if (!$id) {
            Log::warning("⚠️ Punch Type ID not found for: {$type}");
        } else {
            Log::info("✅ Fetched Punch Type ID: {$id} for type: {$type}");
        }

        return $id;
    }
    /**
     * Ensure at least one Clock In and one Clock Out punch exists per day before migration (excluding Holidays).
     */
    private function hasStartAndStopTime(int $employeeId, string $punchTime): bool
    {
        $date = Carbon::parse($punchTime)->toDateString();

        // ✅ Check if a holiday attendance record exists for the given employee and date
        $holidayRecords = Attendance::where('employee_id', $employeeId)
            ->whereDate('punch_time', $date)
            ->whereNotNull('holiday_id')
            ->get();

        Log::info("PM16 🔍 Checking for holiday attendance: Employee ID {$employeeId}, Date {$date}, Found: " . $holidayRecords->count());

        return $holidayRecords->isNotEmpty() || Attendance::where('employee_id', $employeeId)
                ->whereDate('punch_time', $date)
                ->whereIn('punch_type_id', [$this->getPunchTypeId('Clock In'), $this->getPunchTypeId('Clock Out')])
                ->count() >= 2;
    }
}
