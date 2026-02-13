<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceStatusUpdateService
{
    public function markRecordsAsComplete(array $attendanceIds): void
    {
        if (empty($attendanceIds)) {
            return;
        }

        // Use a direct update with subquery instead of loading records
        $updated = Attendance::whereIn('id', $attendanceIds)
            ->whereNotNull('punch_type_id')
            ->whereNotNull('punch_state')
            ->update(['status' => 'Complete']);

        Log::info("[AttendanceStatusUpdateService] Updated {$updated} attendance records to Complete.");
    }

    public function reevaluateNeedsReviewRecords(PayPeriod $payPeriod): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        Log::info("[AttendanceStatusUpdateService] Re-evaluating NeedsReview records for PayPeriod ID: {$payPeriod->id}");

        // Use a direct update instead of loading records into memory
        $updated = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'NeedsReview')
            ->whereNotNull('punch_type_id')
            ->whereNotNull('punch_state')
            ->whereIn('punch_state', ['start', 'stop'])
            ->update(['status' => 'Complete']);

        Log::info("[AttendanceStatusUpdateService] Updated {$updated} NeedsReview records to Complete.");
    }
}
