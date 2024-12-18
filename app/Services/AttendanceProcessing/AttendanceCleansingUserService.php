<?php

namespace App\Services\AttendanceProcessing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\ShiftSchedule;
use Illuminate\Support\Facades\Log;

class AttendanceCleansingUserService
{
    /**
     * Process attendance records for employees with unique schedules.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function processUserSchedulesWithinPayPeriod(PayPeriod $payPeriod): void
    {
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'Incomplete')
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy('employee_id');

        foreach ($attendances as $employeeId => $punches) {
            $schedule = ShiftSchedule::where('employee_id', $employeeId)->first();

            if ($schedule) {
                $this->processEmployeePunches($punches, $schedule); // Existing even punch logic
                $this->processEmployeeOddPunches($punches, $schedule); // New odd punch logic
            } else {
                Log::warning("No schedule for Employee ID: {$employeeId}");
                foreach ($punches as $punch) {
                    // Do nothing: Placeholder for future logic or intentional no-op
                }
            }
        }
    }

    /**
     * Process punches for an employee.
     *
     * @param Collection $punches
     * @param ShiftSchedule $schedule
     * @return void
     */
    private function processEmployeePunches($punches, $schedule): void
    {
        $punchesByDay = $punches->groupBy(fn($punch) => (new \DateTime($punch->punch_time))->format('Y-m-d'));

        foreach ($punchesByDay as $day => $dailyPunches) {
            $dailyPunches = $dailyPunches->sortBy('punch_time')->values();
            Log::info("Processing punches for Employee ID: {$schedule->employee_id} on Date: {$day}");

            // Check if punches are odd-numbered
            $isOdd = $dailyPunches->count() % 2 !== 0;

            if ($isOdd) {
                Log::info("Odd punches detected. Assigning based on schedule for Employee ID: {$schedule->employee_id}.");
            }

            // Assign Clock In and Clock Out
            $this->assignClockInAndClockOut($dailyPunches, $isOdd);

            // Assign Lunch and Breaks (only if even pairs exist)
            if (!$isOdd) {
                $this->assignLunchAndBreakPairs($dailyPunches, $schedule);
            } else {
                // Mark odd punches as partial
                foreach ($dailyPunches as $punch) {
                    $punch->status = 'Partial';
                    $punch->issue_notes = 'Odd punch count. User Assigned based on schedule.';
                    $punch->save();
                }
            }
        }
    }
    private function processEmployeeOddPunches($punches, $schedule): void
    {
        $punchesByDay = $punches->groupBy(fn($punch) => (new \DateTime($punch->punch_time))->format('Y-m-d'));

        foreach ($punchesByDay as $day => $dailyPunches) {
            $dailyPunches = $dailyPunches->sortBy('punch_time')->values();
            Log::info("Processing odd punches for Employee ID: {$schedule->employee_id} on Date: {$day}");

            if ($dailyPunches->count() % 2 === 0) {
                continue; // Skip if punches are even
            }

            foreach ($dailyPunches as $punch) {
                $closestType = $this->findClosestPunchTypeToSchedule($punch, $schedule);

                $punch->punch_type_id = $this->getPunchTypeId($closestType);
                $punch->status = 'Partial';
                $punch->issue_notes = "Assigned: {$closestType}User (Odd Punch)";
                $punch->save();

                Log::info("Assigned: User {$closestType} to Record ID: {$punch->id} (Odd punch processing)");
            }
        }
    }
    /**
     * Assign Clock In and Clock Out punches.
     *
     * @param Collection $punches
     * @param bool $isOdd
     * @return void
     */
    private function assignClockInAndClockOut(Collection $punches, bool $isOdd = false): void
    {
        $firstPunch = $punches->first();
        $firstPunch->punch_type_id = $this->getPunchTypeId('Clock In');
        $firstPunch->issue_notes = 'Assigned: User Clock In';
        $firstPunch->status = 'Complete';
        $firstPunch->save();

        if (!$isOdd && $punches->count() > 1) {
            $lastPunch = $punches->last();
            $lastPunch->punch_type_id = $this->getPunchTypeId('Clock Out');
            $lastPunch->issue_notes = 'Assigned: User Clock Out';
            $lastPunch->status = 'Complete';
            $lastPunch->save();

            Log::info("Assigned User Clock In to Record ID: {$firstPunch->id}, Clock Out to Record ID: {$lastPunch->id}");
        } else {
            Log::info("Assigned User Clock In to Record ID: {$firstPunch->id} for Odd punches.");
        }
    }

    /**
     * Assign Lunch and Break punches.
     *
     * @param Collection $punches
     * @param ShiftSchedule $schedule
     * @return void
     */
    private function assignLunchAndBreakPairs(Collection $punches, ShiftSchedule $schedule): void
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
     * Assign a pair of punches with specified punch types.
     *
     * @param Collection $pair
     * @param string $startType
     * @param string $stopType
     * @return void
     */
    private function assignPunchTypePair(Collection $pair, string $startType, string $stopType): void
    {
        $pair->first()->punch_type_id = $this->getPunchTypeId($startType);
        $pair->last()->punch_type_id = $this->getPunchTypeId($stopType);
        $pair->first()->issue_notes = "Assigned: User{$startType}";
        $pair->last()->issue_notes = "Assigned: User{$stopType}";
        $pair->first()->status = 'Complete';
        $pair->last()->status = 'Complete';
        $pair->first()->save();
        $pair->last()->save();

        Log::info("Assigned User{$startType} to Record ID: {$pair->first()->id}, {$stopType} to Record ID: {$pair->last()->id}");
    }

    /**
     * Find the closest pair of punches to a specific schedule time.
     *
     * @param Collection $pairs
     * @param string $scheduleTime
     * @return Collection|null
     */
    private function findClosestPairToScheduleTime(Collection $pairs, string $scheduleTime): ?Collection
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
    private function findClosestPunchTypeToSchedule($punch, $schedule): string
    {
        $punchTime = strtotime(date('H:i:s', strtotime($punch->punch_time)));

        // Define key schedule times
        $times = [
            'Clock In' => strtotime($schedule->start_time),
            'Lunch Start' => strtotime($schedule->lunch_start_time),
            'Lunch Stop' => strtotime($schedule->lunch_stop_time),
            'Clock Out' => strtotime($schedule->end_time),
        ];

        // Find the closest type
        $closestType = null;
        $smallestDiff = PHP_INT_MAX;

        foreach ($times as $type => $time) {
            $diff = abs($punchTime - $time);

            if ($diff < $smallestDiff) {
                $smallestDiff = $diff;
                $closestType = $type;
            }
        }

        Log::info("Punch ID: {$punch->id} | Closest Type: {$closestType} (Time Diff: {$smallestDiff})");
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
}
