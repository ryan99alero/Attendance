<?php
namespace App\Services\TimeGrouping;

use App\Models\AttendanceTimeGroup;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AttendanceTimeGroupService
{
    /**
     * Get or create an attendance_time_group for an employee on a given shift date.
     *
     * @param int $employeeId
     * @param string $shiftDate
     * @param int $systemUserId
     * @return string|null
     */
    public function getOrCreateGroup(int $employeeId, string $shiftDate, int $systemUserId): ?string
    {
        // Check if an entry already exists for the employee on this shift date
        $existingGroup = AttendanceTimeGroup::where('employee_id', $employeeId)
            ->whereDate('shift_date', $shiftDate)
            ->first();

        if ($existingGroup) {
            Log::info("[TimeGroup] Using existing External Group ID: {$existingGroup->external_group_id} for Employee ID: {$employeeId} on $shiftDate");
            return $existingGroup->external_group_id;
        }

        // Generate a unique group ID
        $externalGroupId = Str::uuid()->toString();

        // Ensure the external_group_id does not exceed the database column size
        $maxGroupIdLength = 40; // Adjust if the database column size is different
        if (strlen($externalGroupId) > $maxGroupIdLength) {
            Log::warning("[TimeGroup] Generated external_group_id exceeds the maximum length ({$maxGroupIdLength} chars). Truncating...");
            $externalGroupId = substr($externalGroupId, 0, $maxGroupIdLength);
        }

        // Create a new group entry
        $newGroup = AttendanceTimeGroup::create([
            'employee_id' => $employeeId,
            'shift_date' => $shiftDate,
            'external_group_id' => $externalGroupId,
        ]);

        Log::info("[TimeGroup] Created new Attendance Time Group: {$newGroup->external_group_id} for Employee ID: {$employeeId} on $shiftDate");

        return $newGroup->external_group_id;
    }

    /**
     * Ensures the attendance record has a valid shift_date and external_group_id.
     * Uses punch_time to infer shift_date if not already set.
     *
     * @param \App\Models\Attendance $attendance
     * @param int $systemUserId
     * @return void
     */
    public function getOrCreateShiftDate(\App\Models\Attendance $attendance, int $systemUserId): void
    {
        if (empty($attendance->shift_date)) {
            $attendance->shift_date = \Carbon\Carbon::parse($attendance->punch_time)->toDateString();
        }

        $existingGroup = AttendanceTimeGroup::where('employee_id', $attendance->employee_id)
            ->whereDate('shift_date', $attendance->shift_date)
            ->first();

        if ($existingGroup) {
            $attendance->external_group_id = $existingGroup->external_group_id;
            \Log::info("[TimeGroup] Found existing group for Attendance ID {$attendance->id} - External Group ID: {$existingGroup->external_group_id}");
        } else {
            $externalGroupId = \Illuminate\Support\Str::uuid()->toString();
            $maxGroupIdLength = 40;

            if (strlen($externalGroupId) > $maxGroupIdLength) {
                $externalGroupId = substr($externalGroupId, 0, $maxGroupIdLength);
            }

            AttendanceTimeGroup::create([
                'employee_id'         => $attendance->employee_id,
                'external_group_id'   => $externalGroupId,
                'shift_date'          => $attendance->shift_date,
                'shift_window_start'  => $attendance->punch_time,
                'shift_window_end'    => \Carbon\Carbon::parse($attendance->punch_time)->addHours(8),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $attendance->external_group_id = $externalGroupId;
            \Log::info("[TimeGroup] Created new group for Attendance ID {$attendance->id} - External Group ID: {$externalGroupId}");
        }

        $attendance->save();
    }

    /**
     * Rebuilds all attendance_time_groups based on current attendance and schedule data.
     * Similar to the InsertAttendanceTimeGroups stored procedure.
     */
    public function rebuildTimeGroups(): void
    {
        Log::info("[TimeGroup] 🔁 Rebuilding all Attendance Time Groups...");

        $flexMinutes = DB::table('company_setup')->value('attendance_flexibility_minutes') ?? 30;

        // Clear existing groups
        AttendanceTimeGroup::truncate();

        // Build new records
        $groupedData = DB::table('attendances as a')
            ->join('employees as e', 'a.employee_id', '=', 'e.id')
            ->leftJoin('shift_schedules as ss', function ($join) {
                $join->on('ss.employee_id', '=', 'e.id')
                    ->orOn(DB::raw('(ss.department_id = e.department_id AND ss.employee_id IS NULL)'), DB::raw('true'));
            })
            ->join('shifts as sh', 'ss.shift_id', '=', 'sh.id')
            ->selectRaw('
                MIN(e.id) AS employee_id,
                CONCAT(e.external_id, "_", DATE_FORMAT(
                    CASE
                        WHEN sh.multi_day_shift = 1 AND TIME(a.punch_time) < "12:00:00"
                            THEN DATE_SUB(DATE(a.punch_time), INTERVAL 1 DAY)
                        ELSE DATE(a.punch_time)
                    END, "%Y%m%d"
                )) AS external_group_id,
                CASE
                    WHEN sh.multi_day_shift = 1 AND TIME(a.punch_time) < "12:00:00"
                        THEN DATE_SUB(DATE(a.punch_time), INTERVAL 1 DAY)
                    ELSE DATE(a.punch_time)
                END AS shift_date,
                TIMESTAMP(DATE(a.punch_time), LEAST(ss.start_time, "23:59:59")) AS shift_window_start,
                TIMESTAMP(DATE_ADD(DATE(a.punch_time), INTERVAL 1 DAY), GREATEST(ss.end_time, "00:00:00")) AS shift_window_end,
                TIMESTAMP(DATE(a.punch_time), COALESCE(ss.lunch_start_time, "12:00:00")) AS lunch_start_time,
                TIMESTAMP(DATE(a.punch_time), ADDTIME(COALESCE(ss.lunch_start_time, "12:00:00"), SEC_TO_TIME(COALESCE(ss.lunch_duration, 30) * 60))) AS lunch_end_time
            ')
            ->groupBy('external_group_id', 'shift_date')
            ->get();

        foreach ($groupedData as $group) {
            AttendanceTimeGroup::create([
                'employee_id'         => $group->employee_id,
                'external_group_id'   => $group->external_group_id,
                'shift_date'          => $group->shift_date,
                'shift_window_start'  => $group->shift_window_start,
                'shift_window_end'    => $group->shift_window_end,
                'lunch_start_time'    => $group->lunch_start_time,
                'lunch_end_time'      => $group->lunch_end_time,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        Log::info("[TimeGroup] ✅ Finished rebuilding Attendance Time Groups.");
    }

    /**
     * Wrapper to ensure both shift_date and external_group_id are assigned.
     * Intended for external use where both are required in sync.
     *
     * @param \App\Models\Attendance $attendance
     * @param int $systemUserId
     * @return void
     */
    public function getOrCreateGroupAndAssign(\App\Models\Attendance $attendance, int $systemUserId): void
    {
        Log::info("[TimeGroup] 🧩 Executing getOrCreateGroupAndAssign for Attendance ID: {$attendance->id}");
        $this->getOrCreateShiftDate($attendance, $systemUserId);
        Log::info("[TimeGroup] ✅ Completed getOrCreateGroupAndAssign for Attendance ID: {$attendance->id}");
    }
}
