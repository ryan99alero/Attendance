<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use Illuminate\Support\Facades\Log;

class AttendanceStatusUpdateService
{
    public function markRecordsAsComplete(array $attendanceIds): void
    {
        Log::info("🛠 [AttendanceStatusUpdateService] Marking records as Complete: " . json_encode($attendanceIds));

        if (empty($attendanceIds)) {
            Log::warning("⚠️ No valid attendance IDs provided.");
            return;
        }

        Attendance::whereIn('id', $attendanceIds)->update(['status' => 'Complete']);

        Log::info("✅ [AttendanceStatusUpdateService] Updated " . count($attendanceIds) . " attendance records to status: Complete.");
    }
}
