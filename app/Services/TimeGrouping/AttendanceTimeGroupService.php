<?php
namespace App\Services\TimeGrouping;

use App\Models\AttendanceTimeGroup;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
        $existingGroup = AttendanceTimeGroup::ffwhere('employee_id', $employeeId)
            ->whereDate('shift_date', $shiftDate)
            ->first();

        if ($existingGroup) {
            Log::info("🔄 Using existing External Group ID: {$existingGroup->external_group_id} for Employee ID: {$employeeId} on $shiftDate");
            return $existingGroup->external_group_id;
        }

        // Create a new group entry
        $newGroup = AttendanceTimeGroup::create([
            'employee_id' => $employeeId,
            'shift_date' => $shiftDate,
            'external_group_id' => Str::uuid(), // Generate unique ID
            'created_by' => $systemUserId,
            'updated_by' => $systemUserId,
        ]);

        Log::info("✅ Created new Attendance Time Group: {$newGroup->external_group_id} for Employee ID: {$employeeId} on $shiftDate");

        return $newGroup->external_group_id;
    }
}
