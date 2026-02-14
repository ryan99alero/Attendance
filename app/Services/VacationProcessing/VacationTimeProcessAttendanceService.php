<?php

namespace App\Services\VacationProcessing;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\VacationCalendar;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes VACATION entries only (not holidays).
 * Holidays are now processed separately via HolidayAttendanceService.
 */
class VacationTimeProcessAttendanceService
{
    /**
     * Cache for employee data
     */
    private array $employeeCache = [];

    private ?int $cachedVacationClassificationId = null;

    private ?int $cachedHolidayClassificationId = null;

    public function processVacationDays(string $startDate, string $endDate): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        Log::info("Processing vacation records from {$startDate} to {$endDate}");

        // Pre-load classification IDs
        $this->cachedVacationClassificationId = DB::table('classifications')->where('code', 'VACATION')->value('id');
        $this->cachedHolidayClassificationId = DB::table('classifications')->where('code', 'HOLIDAY')->value('id');

        // Pre-load employees that have vacation records to process
        $this->preloadEmployees($startDate, $endDate);

        // Process VACATION entries only (holiday_template_id IS NULL)
        $vacationCount = $this->processVacationEntries($startDate, $endDate);

        Log::info("Completed processing - {$vacationCount} vacation records processed.");
    }

    /**
     * Process vacation entries from vacation_calendars table.
     * Note: This table now ONLY contains vacation entries (holidays are in holiday_instances).
     */
    private function processVacationEntries(string $startDate, string $endDate): int
    {
        $totalCount = VacationCalendar::whereBetween('vacation_date', [$startDate, $endDate])
            ->where('is_active', true)
            ->where('is_recorded', false)
            ->count();

        Log::info("Found {$totalCount} vacation records to process.");

        $processed = 0;

        foreach (VacationCalendar::whereBetween('vacation_date', [$startDate, $endDate])
            ->where('is_active', true)
            ->where('is_recorded', false)
            ->cursor() as $record) {

            $employee = $this->getEmployee($record->employee_id);
            if (! $employee) {
                continue;
            }

            // Check if a HOLIDAY attendance already exists for this day
            // If so, skip vacation processing (holiday takes precedence)
            $existingHoliday = Attendance::where('employee_id', $employee->id)
                ->whereDate('punch_time', $record->vacation_date)
                ->where('classification_id', $this->cachedHolidayClassificationId)
                ->exists();

            if ($existingHoliday) {
                // Holiday already processed for this day - mark vacation as recorded but don't create attendance
                $record->update(['is_recorded' => true]);
                Log::info("Vacation {$record->id} skipped - holiday attendance exists for employee {$employee->id} on {$record->vacation_date}");

                continue;
            }

            // Check if vacation attendance already exists
            $existingVacation = Attendance::where('employee_id', $employee->id)
                ->whereDate('punch_time', $record->vacation_date)
                ->where('classification_id', $this->cachedVacationClassificationId)
                ->exists();

            if ($existingVacation) {
                $record->update(['is_recorded' => true]);

                continue;
            }

            $shiftSchedule = $this->getShiftScheduleForEmployee($employee);
            if (! $shiftSchedule) {
                Log::warning("Employee {$employee->id} has no shift schedule - skipping vacation {$record->id}");

                continue;
            }

            try {
                $clockInTime = $this->getVacationPunchTime($record, $shiftSchedule);
                $dailyHours = $record->is_half_day ? $shiftSchedule->daily_hours / 2 : $shiftSchedule->daily_hours;
                $clockOutTime = Carbon::parse($clockInTime)->addHours($dailyHours)->format('Y-m-d H:i:s');

                // Generate shift_date and external_group_id for punch migration
                $vacationDate = $record->vacation_date->format('Y-m-d');
                $externalGroupId = "VAC-{$employee->id}-{$vacationDate}";

                // Create with VACATION classification
                $this->createAttendanceRecord(
                    $employee->id,
                    $clockInTime,
                    'Clock In',
                    'Generated from Vacation Calendar (Clock In)',
                    $this->cachedVacationClassificationId,
                    $vacationDate,
                    $externalGroupId
                );

                $this->createAttendanceRecord(
                    $employee->id,
                    $clockOutTime,
                    'Clock Out',
                    'Generated from Vacation Calendar (Clock Out)',
                    $this->cachedVacationClassificationId,
                    $vacationDate,
                    $externalGroupId
                );

                $record->update(['is_recorded' => true]);
                $processed++;

            } catch (Exception $e) {
                Log::error("Failed to process Vacation Record ID: {$record->id} for Employee ID: {$employee->id}. Error: {$e->getMessage()}");
            }
        }

        return $processed;
    }

    /**
     * Pre-load employees that have vacation records to process
     */
    private function preloadEmployees(string $startDate, string $endDate): void
    {
        $employeeIds = VacationCalendar::whereBetween('vacation_date', [$startDate, $endDate])
            ->where('is_active', true)
            ->where('is_recorded', false)
            ->distinct()
            ->pluck('employee_id');

        $employees = Employee::whereIn('id', $employeeIds)->get();
        foreach ($employees as $employee) {
            $this->employeeCache[$employee->id] = $employee;
        }
    }

    /**
     * Get employee from cache or database
     */
    private function getEmployee(int $employeeId): ?Employee
    {
        if (! isset($this->employeeCache[$employeeId])) {
            $this->employeeCache[$employeeId] = Employee::find($employeeId);
        }

        return $this->employeeCache[$employeeId];
    }

    private function createAttendanceRecord(int $employeeId, string $punchTime, string $punchType, string $issueNotes, ?int $classificationId = null, ?string $shiftDate = null, ?string $externalGroupId = null): void
    {
        $punchTypeId = $this->getPunchTypeId($punchType);

        if (! $punchTypeId) {
            return;
        }

        $punchState = ($punchType === 'Clock In') ? 'start' : 'stop';

        Attendance::create([
            'employee_id' => $employeeId,
            'punch_time' => $punchTime,
            'punch_type_id' => $punchTypeId,
            'punch_state' => $punchState,
            'classification_id' => $classificationId ?? $this->cachedVacationClassificationId,
            'shift_date' => $shiftDate,
            'external_group_id' => $externalGroupId,
            'is_manual' => true,
            'status' => 'Complete',
            'issue_notes' => $issueNotes,
        ]);
    }

    private array $punchTypeCache = [];

    private function getPunchTypeId(string $type): ?int
    {
        if (! isset($this->punchTypeCache[$type])) {
            $this->punchTypeCache[$type] = DB::table('punch_types')->where('name', $type)->value('id');
        }

        return $this->punchTypeCache[$type];
    }

    private function getShiftScheduleForEmployee(Employee $employee): ?ShiftSchedule
    {
        return $employee->shift_schedule_id ? $employee->shiftSchedule : null;
    }

    private function getVacationPunchTime(VacationCalendar $vacation, ShiftSchedule $shiftSchedule): string
    {
        try {
            $validatedDate = Carbon::parse($vacation->vacation_date)->format('Y-m-d');
            $validatedTime = Carbon::parse($shiftSchedule->start_time)->format('H:i:s');

            return "{$validatedDate} {$validatedTime}";
        } catch (Exception $e) {
            Log::error("Failed to create punch_time for Vacation ID: {$vacation->id}. Error: {$e->getMessage()}");

            return now()->toDateTimeString();
        }
    }
}
