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
        Log::info("Starting attendance duplicate cleansing process.");

        // Step 1: Group attendance records by employee_id and punch_time
        $duplicates = Attendance::query()
            ->select('employee_id', 'punch_time', 'id', 'punch_type_id', 'is_migrated')
            ->where('is_migrated', false) // Exclude migrated records
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy(function ($attendance) {
                return $attendance->employee_id . '-' . $attendance->punch_time; // Group by employee and punch_time
            });

        // Step 2: Iterate through grouped duplicates and delete excess records
        foreach ($duplicates as $group => $records) {
            if ($records->count() > 1) {
                // Sort records to prioritize deletion of those without punch_type_id
                $recordsToDelete = $records->sortBy([
                    fn($a, $b) => $a->punch_type_id === null ? -1 : 1, // Prioritize records with null punch_type_id for deletion
                    fn($a, $b) => $b->id <=> $a->id, // Within same type, delete the largest ID
                ])->slice(1); // Keep the first record and delete the rest

                foreach ($recordsToDelete as $record) {
                    $record->delete(); // Delete the duplicate record
                    Log::info("Deleted duplicate attendance record ID: {$record->id} for Employee ID: {$record->employee_id}, Punch Time: {$record->punch_time}");
                }
            }
        }

        Log::info("Attendance duplicate cleansing process completed.");
    }
}
