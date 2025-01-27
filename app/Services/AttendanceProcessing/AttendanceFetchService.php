<?php
// Part of new API Solution we are building.

namespace App\Services\AttendanceProcessing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceFetchService
{
    /**
     * Fetch hours worked using the stored procedure.
     */
    public function fetchHoursWorked(string $startDate, string $endDate): array
    {
        try {
            $results = DB::select('CALL GetHoursWorked(?, ?)', [$startDate, $endDate]);

            Log::info("Fetched hours worked using stored procedure", ['count' => count($results)]);

            return $results;
        } catch (\Exception $e) {
            Log::error("Failed to fetch hours worked: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch time-off data using the stored procedure.
     */
    public function fetchTimeOff(string $startDate, string $endDate): array
    {
        try {
            $results = DB::select('CALL GetTimeOff(?, ?)', [$startDate, $endDate]);

            Log::info("Fetched time-off data using stored procedure", ['count' => count($results)]);

            return $results;
        } catch (\Exception $e) {
            Log::error("Failed to fetch time-off data: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch pay period summary using the stored procedure.
     */
    public function fetchPayPeriodSummary(string $startDate, string $endDate): array
    {
        try {
            $results = DB::select('CALL GetPayPeriodSummary(?, ?)', [$startDate, $endDate]);

            Log::info("Fetched pay period summary using stored procedure", ['count' => count($results)]);

            return $results;
        } catch (\Exception $e) {
            Log::error("Failed to fetch pay period summary: " . $e->getMessage());
            return [];
        }
    }
}
