<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\ShiftSchedule;

class AttendanceCleansingUserService
{
    /**
     * Cleanses attendance data for individual user schedules within a pay period.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function processUserSchedulesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        // Get employees with schedules from the shift_schedules table
        $scheduledEmployeeIds = ShiftSchedule::whereNotNull('employee_id')
            ->pluck('employee_id')
            ->toArray();

        // Fetch attendances for employees with schedules
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->whereIn('employee_id', $scheduledEmployeeIds) // Ensure employee has a schedule
            ->where('status', 'Pending') // Only process pending statuses
            ->get();

        foreach ($attendances as $attendance) {
            // Fetch the user's schedule
            $shiftSchedule = ShiftSchedule::where('employee_id', $attendance->employee_id)->first();

            if ($shiftSchedule) {
                // If schedule belongs to a department, process accordingly
                if ($shiftSchedule->department_id) {
                    $this->processDepartmentSchedule($attendance, $shiftSchedule->department_id);
                } else {
                    $this->processIndividualSchedule($attendance, $shiftSchedule);
                }
            } else {
                // Handle cases where no schedule exists for the employee
                $this->handleMissingSchedule($attendance);
            }
        }
    }

    /**
     * Process attendance based on an individual user's schedule.
     *
     * @param Attendance $attendance
     * @param ShiftSchedule $shiftSchedule
     * @return void
     */
    private function processIndividualSchedule(Attendance $attendance, ShiftSchedule $shiftSchedule): void
    {
        // Add your logic to cleanse attendance data based on the individual's schedule
        $attendance->status = 'Valid'; // Mark as valid after processing
        $attendance->save();
    }

    /**
     * Process attendance based on a department's schedule.
     *
     * @param Attendance $attendance
     * @param int $departmentId
     * @return void
     */
    private function processDepartmentSchedule(Attendance $attendance, int $departmentId): void
    {
        // Add your logic for processing department-level schedules
        $attendance->status = 'Reviewed'; // Example status change
        $attendance->save();
    }

    /**
     * Handle cases where no schedule exists for the employee.
     *
     * @param Attendance $attendance
     * @return void
     */
    private function handleMissingSchedule(Attendance $attendance): void
    {
        // Log or take action when no schedule is found for the employee
        $attendance->issue_notes = 'No schedule found for employee.';
        $attendance->status = 'Reviewed'; // Mark as reviewed to flag for manual intervention
        $attendance->save();
    }
}
