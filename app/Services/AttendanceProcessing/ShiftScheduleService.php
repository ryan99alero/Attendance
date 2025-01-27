<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Employee;
use App\Models\ShiftSchedule;
use Illuminate\Support\Facades\Log;

class ShiftScheduleService
{
    /**
     * Retrieve the appropriate shift schedule for a single employee.
     * Prioritize employee-specific shift schedules.
     *
     * @param int $employeeId
     * @return ShiftSchedule|null
     */
    public function getShiftScheduleForEmployee(int $employeeId): ?ShiftSchedule
    {
        // Fetch the employee record
        $employee = Employee::find($employeeId);

        if (!$employee) {
            Log::warning("Employee not found with ID: {$employeeId}");
            return null;
        }

        // Step 1: Check for employee-specific shift schedule
        if ($employee->shift_schedule_id) {
            $employeeSchedule = ShiftSchedule::find($employee->shift_schedule_id);

            if ($employeeSchedule) {
                Log::info("Found employee-specific shift schedule for Employee ID: {$employeeId}");
                return $employeeSchedule;
            }
        }

        // Step 2: Fallback to department-level shift schedule
        $departmentSchedule = ShiftSchedule::where('department_id', $employee->department_id)->first();

        if ($departmentSchedule) {
            Log::info("Using department-level shift schedule for Employee ID: {$employeeId}, Department ID: {$employee->department_id}");
            return $departmentSchedule;
        }

        // Step 3: No schedule found
        Log::warning("No shift schedule found for Employee ID: {$employeeId}");
        return null;
    }

    /**
     * Retrieve shift schedules for multiple employees.
     * This method handles both employee-specific and department-level schedules.
     *
     * @param array $employeeIds
     * @return array
     */
    public function getShiftSchedulesForEmployees(array $employeeIds): array
    {
        $schedules = [];

        foreach ($employeeIds as $employeeId) {
            $schedule = $this->getShiftScheduleForEmployee($employeeId);

            if ($schedule) {
                $schedules[$employeeId] = $schedule;
            }
        }

        return $schedules;
    }

    /**
     * Handle cases where no shift schedule is found.
     * This method can be used to define a default schedule if needed.
     *
     * @return ShiftSchedule|null
     */
    public function getDefaultShiftSchedule(): ?ShiftSchedule
    {
        return ShiftSchedule::where('is_default', true)->first();
    }
}
