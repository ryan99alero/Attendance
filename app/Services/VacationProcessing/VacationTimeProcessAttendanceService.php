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

class VacationTimeProcessAttendanceService
{
    /**
     * Cache for employee data
     */
    private array $employeeCache = [];

    public function processVacationDays(string $startDate, string $endDate): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        Log::info("Processing vacation records from {$startDate} to {$endDate}");

        $totalCount = VacationCalendar::whereBetween('vacation_date', [$startDate, $endDate])
            ->where('is_active', true)
            ->where('is_recorded', false)
            ->count();

        Log::info("Found {$totalCount} vacation records to process.");

        // Pre-load employees that have vacation records to process
        $this->preloadEmployees($startDate, $endDate);

        $processed = 0;

        // Use cursor() to iterate one record at a time - prevents memory exhaustion
        foreach (VacationCalendar::whereBetween('vacation_date', [$startDate, $endDate])
            ->where('is_active', true)
            ->where('is_recorded', false)
            ->cursor() as $record) {
            $employee = $this->getEmployee($record->employee_id);

            if (! $employee) {
                continue;
            }

            // Check if attendance records already exist for this vacation
            $existingAttendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('punch_time', $record->vacation_date)
                ->where('issue_notes', 'like', '%Generated from Vacation Calendar%')
                ->exists();

            if ($existingAttendance) {
                $record->update(['is_recorded' => true]);

                continue;
            }

            $shiftSchedule = $this->getShiftScheduleForEmployee($employee);

            if (! $shiftSchedule) {
                continue;
            }

            try {
                // Create Clock In record
                $clockInTime = $this->getVacationPunchTime($record, $shiftSchedule);
                $this->createAttendanceRecord($employee->id, $clockInTime, 'Clock In', 'Generated from Vacation Calendar (Clock In)');

                // Determine Clock Out time
                $dailyHours = $record->is_half_day ? $shiftSchedule->daily_hours / 2 : $shiftSchedule->daily_hours;
                $clockOutTime = Carbon::parse($clockInTime)->addHours($dailyHours)->format('Y-m-d H:i:s');

                // Create Clock Out record
                $this->createAttendanceRecord($employee->id, $clockOutTime, 'Clock Out', 'Generated from Vacation Calendar (Clock Out)');

                // Mark vacation record as recorded only after both records are created
                $record->update(['is_recorded' => true]);

                $processed++;

            } catch (Exception $e) {
                Log::error("Failed to process Vacation Record ID: {$record->id} for Employee ID: {$employee->id}. Error: {$e->getMessage()}");
            }
        }

        Log::info("Completed vacation processing - {$processed}/{$totalCount} records processed.");
    }

    private ?int $cachedVacationClassificationId = null;

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

    private function createAttendanceRecord(int $employeeId, string $punchTime, string $punchType, string $issueNotes): void
    {
        $punchTypeId = $this->getPunchTypeId($punchType);

        if (! $punchTypeId) {
            return;
        }

        // Cache vacation classification ID
        if ($this->cachedVacationClassificationId === null) {
            $this->cachedVacationClassificationId = DB::table('classifications')->where('code', 'VACATION')->value('id');
        }

        $punchState = ($punchType === 'Clock In') ? 'start' : 'stop';

        Attendance::create([
            'employee_id' => $employeeId,
            'punch_time' => $punchTime,
            'punch_type_id' => $punchTypeId,
            'punch_state' => $punchState,
            'classification_id' => $this->cachedVacationClassificationId,
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
