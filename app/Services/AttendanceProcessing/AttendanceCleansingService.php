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
        Log::info("🔍 Starting attendance duplicate cleansing process...");

        // Step 1: Fetch potential duplicate attendance records
        $duplicates = Attendance::query()
            ->select('id', 'employee_id', 'punch_time', 'punch_type_id', 'is_migrated')
            ->where('is_migrated', false) // Exclude migrated records
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy(fn($attendance) => "{$attendance->employee_id}-{$attendance->punch_time}"); // Group by employee & punch time

        // Step 2: Iterate through groups & remove duplicate records
        foreach ($duplicates as $group => $records) {
            if ($records->count() > 1) {
                Log::warning("⚠️ Found duplicate records for Employee ID: {$records->first()->employee_id}, Punch Time: {$records->first()->punch_time}");

                // Prioritize deletion of records without punch_type_id, keeping the lowest ID record
                $recordsToDelete = $records->sortBy([
                    fn($a, $b) => $a->punch_type_id === null ? -1 : 1, // Prioritize deletion of null punch_type_id records
                    fn($a, $b) => $b->id <=> $a->id, // Delete the highest ID (newest duplicate)
                ])->slice(1); // Keep the first valid record

                foreach ($recordsToDelete as $record) {
                    $record->delete();
                    Log::info("🗑 Deleted duplicate record ID: {$record->id} for Employee ID: {$record->employee_id}, Punch Time: {$record->punch_time}");
                }
            }
        }

        Log::info("✅ Attendance duplicate cleansing process completed.");
    }
}
