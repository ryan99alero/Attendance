<?php

namespace App\Services\AttendanceProcessing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\ShiftSchedule;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;

class AttendanceCleansingGroupService
{
    /**
     * Process attendance records for employees under group schedules.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function processGroupSchedulesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("Starting Group Attendance Processing for PayPeriod: {$payPeriod->id}");

        // Step 1: Fetch attendance records for the pay period
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->whereIn('status', ['Incomplete', 'Partial'])
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy('employee_id');

        Log::info("Fetched " . $attendances->count() . " employees' attendance records.");

        foreach ($attendances as $employeeId => $punches) {
            Log::info("Processing Employee ID: {$employeeId}");

            // Step 2: Find the employee
            $employee = Employee::find($employeeId);

            if (!$employee) {
                Log::warning("Employee ID: {$employeeId} not found.");
                continue;
            }

            Log::info("Employee ID: {$employeeId} belongs to Department ID: {$employee->department_id}");

            // Step 3: Find the group shift schedule
            $schedule = $this->getGroupShiftSchedule($employee);

            if ($schedule) {
                Log::info("Group Schedule Found: Schedule ID: {$schedule->id} for Employee ID: {$employeeId}");
                $this->processGroupPunches($punches, $schedule);
            } else {
                Log::warning("No Group Shift Schedule Found for Employee ID: {$employeeId}");
                foreach ($punches as $punch) {
                    $punch->status = 'Partial';
                    $punch->issue_notes = 'No group schedule found';
                    $punch->save();
                }
            }
        }
    }
    /**
     * Retrieve the group shift schedule for an employee.
     *
     * @param Employee $employee
     * @return ShiftSchedule|null
     */
    /**
     * Get the group shift schedule for an employee.
     *
     * @param Employee $employee
     * @return ShiftSchedule|null
     */
    private function getGroupShiftSchedule(Employee $employee): ?ShiftSchedule
    {
        // Step 1: Check for a custom shift schedule (should skip employees with a custom schedule)
        if ($employee->shift_schedule_id) {
            Log::info("Employee ID: {$employee->id} has a custom shift schedule. Skipping for Group Processing.");
            return null;
        }

        // Step 2: Fetch the group shift schedule based on department
        Log::info("Looking up Group Schedule for Department ID: {$employee->department_id}");
        $schedule = ShiftSchedule::where('department_id', $employee->department_id)->first();

        if ($schedule) {
            Log::info("Found Group Schedule ID: {$schedule->id} for Department ID: {$employee->department_id}");
        } else {
            Log::warning("No Group Schedule found for Department ID: {$employee->department_id}");
        }

        return $schedule;
    }

    /**
     * Process punches for an employee under a group schedule.
     *
     * @param Collection $punches
     * @param ShiftSchedule $schedule
     * @return void
     */
    private function processGroupPunches(Collection $punches, ShiftSchedule $schedule): void
    {
        $punchesByDay = $punches->groupBy(fn($punch) => (new \DateTime($punch->punch_time))->format('Y-m-d'));

        foreach ($punchesByDay as $day => $dailyPunches) {
            $dailyPunches = $dailyPunches->sortBy('punch_time')->values();
            Log::info("Processing punches for Employee ID: {$schedule->employee_id} on Date: {$day}");

            $isOdd = $dailyPunches->count() % 2 !== 0;

            if ($isOdd) {
                $this->processOddPunches($dailyPunches, $schedule);
            } else {
                $this->assignClockInAndClockOut($dailyPunches);
                $this->assignLunchAndBreakPairs($dailyPunches, $schedule);
            }
        }
    }

    /**
     * Process odd-numbered punches by assigning closest punch types.
     *
     * @param Collection $punches
     * @param ShiftSchedule $schedule
     * @return void
     */
    private function processOddPunches(Collection $punches, ShiftSchedule $schedule): void
    {
        foreach ($punches as $punch) {
            $closestType = $this->findClosestPunchTypeToSchedule($punch, $schedule);

            $punch->punch_type_id = $this->getPunchTypeId($closestType);
            $punch->status = 'Partial';
            $punch->issue_notes = "Assigned: {$closestType} Group (Odd Punch)";
            $punch->save();

            Log::info("Assigned {$closestType} to Record ID: {$punch->id} (Odd punch processing)");
        }
    }

    /**
     * Assign Clock In and Clock Out punches.
     *
     * @param Collection $punches
     * @return void
     */
    private function assignClockInAndClockOut($punches): void
    {
        $firstPunch = $punches->first();
        $firstPunch->punch_type_id = $this->getPunchTypeId('Clock In');
        $firstPunch->issue_notes = 'Assigned:Group Clock In';
        $firstPunch->status = 'Complete';
        $firstPunch->save();

        $lastPunch = $punches->last();
        $lastPunch->punch_type_id = $this->getPunchTypeId('Clock Out');
        $lastPunch->issue_notes = 'Assigned:Group Clock Out';
        $lastPunch->status = 'Complete';
        $lastPunch->save();

        Log::info("Assigned Clock In to Record ID: {$firstPunch->id}, Clock Out to Record ID: {$lastPunch->id}");
    }

    /**
     * Assign Lunch and Break punches.
     *
     * @param Collection $punches
     * @param ShiftSchedule $schedule
     * @return void
     */
    private function assignLunchAndBreakPairs($punches, $schedule): void
    {
        // Exclude the first (Clock In) and last (Clock Out) punches
        $remainingPairs = $punches->slice(1, -1)->chunk(2);

        $lunchStartTime = $schedule->lunch_start_time; // Lunch Start Time
        Log::info("Scheduled Lunch Start Time: {$lunchStartTime}");

        $lunchPair = $this->findClosestPairToScheduleTime($remainingPairs, $lunchStartTime);

        // Assign Lunch Start/Stop
        if ($lunchPair) {
            $this->assignPunchTypePair($lunchPair, 'Lunch Start', 'Lunch Stop');
        }

        // Assign remaining pairs as Break Start/Stop
        foreach ($remainingPairs as $pair) {
            if ($pair === $lunchPair || $pair->count() !== 2) {
                continue;
            }
            $this->assignPunchTypePair($pair, 'Break Start', 'Break Stop');
        }
    }

    /**
     * Find the closest punch type to a schedule time.
     *
     * @param \App\Models\Attendance $punch
     * @param ShiftSchedule $schedule
     * @return string
     */
    private function findClosestPunchTypeToSchedule($punch, $schedule): string
    {
        $punchTime = strtotime(date('H:i:s', strtotime($punch->punch_time)));

        $times = [
            'Clock In' => strtotime($schedule->start_time),
            'Lunch Start' => strtotime($schedule->lunch_start_time),
            'Lunch Stop' => strtotime($schedule->lunch_stop_time),
            'Clock Out' => strtotime($schedule->end_time),
        ];

        $closestType = null;
        $smallestDiff = PHP_INT_MAX;

        foreach ($times as $type => $time) {
            $diff = abs($punchTime - $time);
            if ($diff < $smallestDiff) {
                $smallestDiff = $diff;
                $closestType = $type;
            }
        }

        return $closestType;
    }

    /**
     * Retrieve the punch type ID by name.
     *
     * @param string $type
     * @return int|null
     */
    private function getPunchTypeId(string $type): ?int
    {
        return DB::table('punch_types')->where('name', $type)->value('id');
    }
    /**
     * Find the closest pair of punches to a specific schedule time.
     *
     * @param \Illuminate\Support\Collection $pairs
     * @param string $scheduleTime
     * @return \Illuminate\Support\Collection|null
     */
    private function findClosestPairToScheduleTime($pairs, $scheduleTime): ?\Illuminate\Support\Collection
    {
        $lunchStartSeconds = strtotime($scheduleTime);
        $closestPair = null;
        $smallestTimeDiff = PHP_INT_MAX;

        Log::info("Comparing remaining pairs to Lunch Start: {$scheduleTime}");

        foreach ($pairs as $pair) {
            if ($pair->count() !== 2) {
                Log::warning("Skipping incomplete pair.");
                continue;
            }

            // Extract time components for comparison
            $punch1Time = date('H:i:s', strtotime($pair->first()->punch_time));
            $punch2Time = date('H:i:s', strtotime($pair->last()->punch_time));
            $punch1Seconds = strtotime($punch1Time);
            $timeDiff = abs($punch1Seconds - $lunchStartSeconds);

            Log::info("Checking Pair: {$punch1Time} - {$punch2Time} | Time Diff = {$timeDiff}");

            // Update closest pair if current one is closer
            if ($timeDiff < $smallestTimeDiff) {
                $smallestTimeDiff = $timeDiff;
                $closestPair = $pair;
                Log::info("Updated Closest Lunch Pair: {$punch1Time} - {$punch2Time} (Time Diff = {$timeDiff})");
            }
        }

        if ($closestPair) {
            Log::info("Final Closest Pair to Schedule Time: {$scheduleTime} has Time Diff: {$smallestTimeDiff}");
        } else {
            Log::warning("No valid pair found close to Lunch Start: {$scheduleTime}");
        }

        return $closestPair;
    }
}
