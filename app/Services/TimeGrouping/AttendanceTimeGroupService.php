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
            'created_by' => $systemUserId,
            'updated_by' => $systemUserId,
        ]);

        Log::info("[TimeGroup] Created new Attendance Time Group: {$newGroup->external_group_id} for Employee ID: {$employeeId} on $shiftDate");

        return $newGroup->external_group_id;
    }
}
