<?php

namespace App\Services\HolidayProcessing;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\HolidayInstance;
use App\Models\PayPeriod;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes holidays from HolidayInstance directly to Attendance records.
 * No longer uses VacationCalendar as an intermediary.
 */
class HolidayAttendanceService
{
    private ?int $holidayClassificationId = null;

    private ?int $clockInTypeId = null;

    private ?int $clockOutTypeId = null;

    /**
     * Process all holidays within a pay period.
     */
    public function processHolidaysForPayPeriod(PayPeriod $payPeriod): int
    {
        DB::disableQueryLog();

        $startDate = Carbon::parse($payPeriod->start_date);
        $endDate = Carbon::parse($payPeriod->end_date);

        Log::info("[HolidayAttendanceService] Processing holidays for PayPeriod {$payPeriod->id} ({$startDate->toDateString()} to {$endDate->toDateString()})");

        // Cache lookup values
        $this->cacheLookupValues();

        // Find all active holidays within the pay period date range
        $holidays = HolidayInstance::active()
            ->forDateRange($startDate, $endDate)
            ->get();

        if ($holidays->isEmpty()) {
            Log::info('[HolidayAttendanceService] No holidays found in pay period date range');

            return 0;
        }

        Log::info("[HolidayAttendanceService] Found {$holidays->count()} holiday(s) to process");

        $totalProcessed = 0;

        foreach ($holidays as $holiday) {
            $processed = $this->processHoliday($holiday);
            $totalProcessed += $processed;
        }

        Log::info("[HolidayAttendanceService] Completed - {$totalProcessed} employee holiday records created");

        return $totalProcessed;
    }

    /**
     * Process a single holiday for all eligible employees.
     */
    public function processHoliday(HolidayInstance $holiday): int
    {
        Log::info("[HolidayAttendanceService] Processing holiday: {$holiday->name} on {$holiday->holiday_date->toDateString()}");

        // Get all active employees and filter by eligibility
        $employees = Employee::where('is_active', true)->get();
        $processed = 0;

        foreach ($employees as $employee) {
            // Check eligibility based on pay type
            if (! $holiday->appliesToEmployee($employee)) {
                continue;
            }

            // Check if attendance already exists for this employee/date
            $existingAttendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('punch_time', $holiday->holiday_date)
                ->where('classification_id', $this->holidayClassificationId)
                ->exists();

            if ($existingAttendance) {
                continue; // Already processed
            }

            // Get employee's shift schedule for clock in/out times
            $shiftSchedule = $employee->shiftSchedule;
            if (! $shiftSchedule) {
                Log::debug("[HolidayAttendanceService] Employee {$employee->id} has no shift schedule - using defaults");
            }

            try {
                $this->createHolidayAttendance($employee, $holiday, $shiftSchedule);
                $processed++;
            } catch (Exception $e) {
                Log::error("[HolidayAttendanceService] Failed to process Employee {$employee->id} for holiday {$holiday->id}: {$e->getMessage()}");
            }
        }

        Log::info("[HolidayAttendanceService] Holiday '{$holiday->name}': {$processed} employees processed");

        return $processed;
    }

    /**
     * Create attendance records for an employee's holiday.
     */
    private function createHolidayAttendance(Employee $employee, HolidayInstance $holiday, $shiftSchedule): void
    {
        $holidayDate = $holiday->holiday_date->toDateString();

        // Determine clock in/out times from shift schedule or defaults
        // Note: start_time may be stored as datetime, so extract just the time portion
        $startTime = '08:00:00';
        if ($shiftSchedule?->start_time) {
            $startTime = Carbon::parse($shiftSchedule->start_time)->format('H:i:s');
        }
        $dailyHours = $holiday->standard_hours ?? $shiftSchedule?->daily_hours ?? 8.0;

        $clockInTime = "{$holidayDate} {$startTime}";
        $clockOutTime = Carbon::parse($clockInTime)->addHours($dailyHours)->format('Y-m-d H:i:s');

        // Generate external_group_id for this employee/date
        $externalGroupId = "HOL-{$employee->id}-{$holidayDate}";

        // Create Clock In
        Attendance::create([
            'employee_id' => $employee->id,
            'punch_time' => $clockInTime,
            'punch_type_id' => $this->clockInTypeId,
            'punch_state' => 'start',
            'classification_id' => $this->holidayClassificationId,
            'holiday_id' => $holiday->id,
            'shift_date' => $holidayDate,
            'external_group_id' => $externalGroupId,
            'is_manual' => true,
            'status' => 'Complete',
            'issue_notes' => "Generated from Holiday: {$holiday->name} (Clock In)",
        ]);

        // Create Clock Out
        Attendance::create([
            'employee_id' => $employee->id,
            'punch_time' => $clockOutTime,
            'punch_type_id' => $this->clockOutTypeId,
            'punch_state' => 'stop',
            'classification_id' => $this->holidayClassificationId,
            'holiday_id' => $holiday->id,
            'shift_date' => $holidayDate,
            'external_group_id' => $externalGroupId,
            'is_manual' => true,
            'status' => 'Complete',
            'issue_notes' => "Generated from Holiday: {$holiday->name} (Clock Out)",
        ]);
    }

    /**
     * Cache lookup values to avoid repeated queries.
     */
    private function cacheLookupValues(): void
    {
        $this->holidayClassificationId = DB::table('classifications')->where('code', 'HOLIDAY')->value('id');
        $this->clockInTypeId = DB::table('punch_types')->where('name', 'Clock In')->value('id');
        $this->clockOutTypeId = DB::table('punch_types')->where('name', 'Clock Out')->value('id');
    }
}
