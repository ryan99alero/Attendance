<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;

class AttendanceTimeProcessorService
{
    protected ShiftScheduleService $shiftScheduleService;

    /**
     * Constructor to inject ShiftScheduleService.
     *
     * @param ShiftScheduleService $shiftScheduleService
     */
    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
        Log::info("Initialized AttendanceTimeProcessorService.");
    }

    /**
     * Process attendance records for a given pay period.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function processAttendanceForPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("Starting attendance import for PayPeriod ID: {$payPeriod->id}");

        // Fetch incomplete attendance records
        $attendances = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('status', 'Incomplete')
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy('employee_id');

        Log::info("Fetched attendance records for {$attendances->count()} employees.");

        foreach ($attendances as $employeeId => $punches) {
            Log::info("Processing Employee ID: {$employeeId} with {$punches->count()} punch(es).");

            // Fetch shift schedule for the employee
            $schedule = $this->shiftScheduleService->getShiftScheduleForEmployee($employeeId);

            if ($schedule) {
                Log::info("Found shift schedule for Employee ID: {$employeeId} (Schedule ID: {$schedule->id}).");
                $this->processPunchesForEmployee($punches, $schedule);
            } else {
                Log::warning("No shift schedule found for Employee ID: {$employeeId}. Marking records as Partial.");
                foreach ($punches as $punch) {
                    $punch->status = 'Partial';
                    $punch->issue_notes = 'No shift schedule found';
                    $punch->save();
                }
            }
        }

        Log::info("Attendance import completed for PayPeriod ID: {$payPeriod->id}");
    }

    /**
     * Process punches for an employee under a specific shift schedule.
     *
     * @param \Illuminate\Support\Collection $punches
     * @param $schedule
     * @return void
     */
    private function processPunchesForEmployee($punches, $schedule): void
    {
        Log::info("Processing punches for Employee ID: {$schedule->employee_id}.");

        $punchesByDay = $punches->groupBy(fn($punch) => (new \DateTime($punch->punch_time))->format('Y-m-d'));

        foreach ($punchesByDay as $day => $dailyPunches) {
            Log::info("Processing punches for Date: {$day} with {$dailyPunches->count()} punch(es).");
            $dailyPunches = $dailyPunches->sortBy('punch_time')->values();

            $isOdd = $dailyPunches->count() % 2 !== 0;

            if ($isOdd) {
                Log::warning("Odd punch count detected for Employee ID: {$schedule->employee_id} on Date: {$day}");
                $this->handleOddPunches($dailyPunches, $schedule);
                continue;
            }

            $this->assignPunchTypes($dailyPunches, $schedule);
        }
    }

    /**
     * Handle odd-numbered punches by marking as Partial.
     *
     * @param \Illuminate\Support\Collection $punches
     * @param $schedule
     * @return void
     */
    private function handleOddPunches($punches, $schedule): void
    {
        Log::info("Handling odd punches for Employee ID: {$schedule->employee_id}.");

        foreach ($punches as $punch) {
            $punch->status = 'Partial';
            $punch->issue_notes = 'Odd punch count detected. Manual intervention required.';
            $punch->save();

            Log::info("Marked Punch ID: {$punch->id} as Partial due to odd punch count.");
        }
    }

    /**
     * Assign punch types (Clock In, Lunch, Clock Out) based on the schedule.
     *
     * @param \Illuminate\Support\Collection $punches
     * @param $schedule
     * @return void
     */
    private function assignPunchTypes($punches, $schedule): void
    {
        Log::info("Assigning punch types for Employee ID: {$schedule->employee_id}.");

        $firstPunch = $punches->first();
        $firstPunch->punch_type_id = $this->getPunchTypeId('Clock In');
        $firstPunch->status = 'Complete';
        $firstPunch->issue_notes = 'Assigned Clock In';
        $firstPunch->save();

        $lastPunch = $punches->last();
        $lastPunch->punch_type_id = $this->getPunchTypeId('Clock Out');
        $lastPunch->status = 'Complete';
        $lastPunch->issue_notes = 'Assigned Clock Out';
        $lastPunch->save();

        Log::info("Assigned Clock In (Punch ID: {$firstPunch->id}) and Clock Out (Punch ID: {$lastPunch->id}).");

        $remainingPairs = $punches->slice(1, -1)->chunk(2);
        $this->assignLunchAndBreakPunches($remainingPairs, $schedule);
    }

    /**
     * Assign Lunch and Break punch types.
     *
     * @param \Illuminate\Support\Collection $pairs
     * @param $schedule
     * @return void
     */
    private function assignLunchAndBreakPunches($pairs, $schedule): void
    {
        Log::info("Assigning lunch and break punches for Employee ID: {$schedule->employee_id}.");

        foreach ($pairs as $pair) {
            if ($pair->count() !== 2) {
                Log::warning("Incomplete punch pair found. Skipping assignment.");
                continue;
            }

            $pair->first()->punch_type_id = $this->getPunchTypeId('Lunch Start');
            $pair->first()->status = 'Complete';
            $pair->first()->issue_notes = 'Assigned Lunch Start';
            $pair->first()->save();

            $pair->last()->punch_type_id = $this->getPunchTypeId('Lunch Stop');
            $pair->last()->status = 'Complete';
            $pair->last()->issue_notes = 'Assigned Lunch Stop';
            $pair->last()->save();

            Log::info("Assigned Lunch Start (Punch ID: {$pair->first()->id}) and Lunch Stop (Punch ID: {$pair->last()->id}).");
        }
    }

    /**
     * Retrieve the punch type ID by name.
     *
     * @param string $type
     * @return int|null
     */
    private function getPunchTypeId(string $type): ?int
    {
        $id = \DB::table('punch_types')->where('name', $type)->value('id');
        Log::info("Retrieved Punch Type ID: {$id} for type: {$type}.");
        return $id;
    }
}
