<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
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

        // Step 1: Fetch potential duplicate attendance records
        $duplicates = Attendance::query()
            ->select('id', 'employee_id', 'punch_time', 'punch_type_id', 'punch_state', 'is_migrated')
            ->where('is_migrated', false) // Exclude migrated records
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy(fn($attendance) => "{$attendance->employee_id}-{$attendance->punch_time}"); // Group by employee & punch time

        $totalDeleted = 0;

        // Step 2: Iterate through groups & remove duplicate records
        foreach ($duplicates as $group => $records) {
            if ($records->count() > 1) {
                Log::warning("[AttendanceCleansingService] ⚠️ Found duplicate records for Employee ID: {$records->first()->employee_id}, Punch Time: {$records->first()->punch_time}");

                // Prioritize deletion of records without punch_type_id or punch_state
                $recordsToDelete = $records->sortBy([
                    fn($a, $b) => ($a->punch_type_id === null || $a->punch_state === null) ? -1 : 1, // Delete those missing data first
                    fn($a, $b) => $b->id <=> $a->id, // Delete the highest ID (newest duplicate)
                ])->slice(1); // Keep the first valid record

                // Collect IDs for bulk deletion
                $deleteIds = $recordsToDelete->pluck('id')->toArray();

                if (!empty($deleteIds)) {
                    Attendance::whereIn('id', $deleteIds)->delete();
                    $totalDeleted += count($deleteIds);
                    Log::info("[AttendanceCleansingService] 🗑 Deleted " . count($deleteIds) . " duplicate records for Employee ID: {$records->first()->employee_id}, Punch Time: {$records->first()->punch_time}");
                }
            }
        }

        Log::info("[AttendanceCleansingService] ✅ Duplicate cleansing process completed. Total records deleted: {$totalDeleted}");
    }
}
