<?php
// Part of new API Solution we are building.

namespace App\Services\AttendanceProcessing;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

class AttendanceFetchService
{
    /**
     * Fetch hours worked using the stored procedure.
     */
    public function fetchHoursWorked(string $startDate, string $endDate): array
    {
        return $this->executeStoredProcedure('CALL GetHoursWorked(?, ?)', [$startDate, $endDate], 'hours worked');
    }

    /**
     * Fetch time-off data using the stored procedure.
     */
    public function fetchTimeOff(string $startDate, string $endDate): array
    {
        return $this->executeStoredProcedure('CALL GetTimeOff(?, ?)', [$startDate, $endDate], 'time-off data');
    }

    /**
     * Fetch pay period summary using the stored procedure.
     */
    public function fetchPayPeriodSummary(string $startDate, string $endDate): array
    {
        return $this->executeStoredProcedure('CALL GetPayPeriodSummary(?, ?)', [$startDate, $endDate], 'pay period summary');
    }

    /**
     * Generic method to execute stored procedures with enhanced error handling.
     */
    private function executeStoredProcedure(string $query, array $params, string $processType): array
    {
        try {
            $startTime = Carbon::now();

            $results = DB::select($query, $params);
            $duration = $startTime->diffInMilliseconds(Carbon::now());

            Log::info("[AttendanceFetchService] ✅ Successfully fetched {$processType} using stored procedure.", [
                'count' => count($results),
                'execution_time_ms' => $duration
            ]);

            return $results ?? [];

        } catch (QueryException $qe) {
            Log::error("[AttendanceFetchService] ❌ SQL Error fetching {$processType}: " . $qe->getMessage());
            return [];

        } catch (Exception $e) {
            Log::error("[AttendanceFetchService] ❌ General Error fetching {$processType}: " . $e->getMessage());
            return [];
        }
    }
}
