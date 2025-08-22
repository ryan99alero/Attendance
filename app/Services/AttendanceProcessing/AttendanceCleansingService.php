<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceCleansingService
{
    /**
     * Clean up duplicate attendance records.
     *
     * @return void
     */
    public function cleanUpDuplicates(): void
    {
        Log::info("[AttendanceCleansingService] 🔍 Starting attendance duplicate cleansing process...");

        DB::transaction(function () {
            // Get IDs of duplicate records to delete using subquery with window function
            $duplicateIds = DB::table('attendances')
                ->select('id')
                ->fromSub(function ($query) {
                    $query->select(
                        'id',
                        'employee_id', 
                        'punch_time',
                        DB::raw('ROW_NUMBER() OVER (
                            PARTITION BY employee_id, punch_time 
                            ORDER BY 
                                CASE WHEN punch_type_id IS NOT NULL AND punch_state IS NOT NULL THEN 0 ELSE 1 END,
                                id ASC
                        ) as row_num')
                    )
                    ->from('attendances')
                    ->where('is_migrated', false);
                }, 'ranked')
                ->where('row_num', '>', 1)
                ->pluck('id')
                ->toArray();

            // Delete duplicate records
            $deletedCount = 0;
            if (!empty($duplicateIds)) {
                $deletedCount = Attendance::whereIn('id', $duplicateIds)->delete();
            }

            Log::info("[AttendanceCleansingService] ✅ Duplicate cleansing process completed. Total records deleted: $deletedCount");
        });
    }
}
