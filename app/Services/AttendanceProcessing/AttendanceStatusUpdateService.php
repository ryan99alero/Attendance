<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use Illuminate\Support\Facades\Log;

class AttendanceStatusUpdateService
{
    public function markRecordsAsComplete(array $attendanceIds): void
    {
        Log::info("[AttendanceStatusUpdateService] 🛠 Marking records as Complete: " . json_encode($attendanceIds));

        if (empty($attendanceIds)) {
            Log::warning("[AttendanceStatusUpdateService] ⚠️ No valid attendance IDs provided.");
            return;
        }

        // Ensure all records have a valid punch_state before marking them as complete
        $validRecords = Attendance::whereIn('id', $attendanceIds)
            ->whereNotNull('punch_type_id')
            ->whereNotNull('punch_state')
            ->get();

        if ($validRecords->isEmpty()) {
            Log::warning("[AttendanceStatusUpdateService] ⚠️ No records were updated because they are missing a punch_type_id or punch_state.");
            return;
        }

        Attendance::whereIn('id', $validRecords->pluck('id'))->update(['status' => 'Complete']);

        Log::info("[AttendanceStatusUpdateService] ✅ Updated " . $validRecords->count() . " attendance records to status: Complete.");
    }
}
