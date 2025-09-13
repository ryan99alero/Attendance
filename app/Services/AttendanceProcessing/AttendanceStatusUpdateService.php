<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
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

    public function reevaluateNeedsReviewRecords(PayPeriod $payPeriod): void
    {
        Log::info("[AttendanceStatusUpdateService] 🔍 Re-evaluating NeedsReview records for PayPeriod ID: {$payPeriod->id}");

        // Find all NeedsReview records that now have valid punch_type_id and punch_state
        $recordsToUpdate = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'NeedsReview')
            ->whereNotNull('punch_type_id')
            ->whereNotNull('punch_state')
            ->whereIn('punch_state', ['start', 'stop']) // Only valid punch states
            ->get(['id']);

        if ($recordsToUpdate->isEmpty()) {
            Log::info("[AttendanceStatusUpdateService] ✅ No NeedsReview records found that can be updated to Complete.");
            return;
        }

        $recordIds = $recordsToUpdate->pluck('id')->toArray();
        Log::info("[AttendanceStatusUpdateService] 🛠 Found " . count($recordIds) . " NeedsReview records that can be marked as Complete: " . json_encode($recordIds));

        // Update the records to Complete status
        $updated = Attendance::whereIn('id', $recordIds)->update(['status' => 'Complete']);

        Log::info("[AttendanceStatusUpdateService] ✅ Updated {$updated} NeedsReview records to Complete status.");
    }
}
