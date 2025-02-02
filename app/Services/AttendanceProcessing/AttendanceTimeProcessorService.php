<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceTimeProcessorService
{
    protected ShiftScheduleService $shiftScheduleService;

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
        Log::info("Initialized AttendanceTimeProcessorService.");
    }

    public function processAttendanceForPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("Starting attendance import for PayPeriod ID: {$payPeriod->id}");

        // Adjust end date logic (exclude last day only if it includes today)
        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        // Fetch incomplete attendance records
        $attendances = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->where('status', 'Incomplete')
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy('employee_id');

        Log::info("Fetched attendance records for {$attendances->count()} employees.");

        foreach ($attendances as $employeeId => $punches) {
            Log::info("Processing Employee ID: {$employeeId} with {$punches->count()} punch(es).");

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
                $this->handleOddPunches($dailyPunches);
                continue;
            }

            $this->assignPunchTypes($dailyPunches, $schedule);
        }
    }

    private function handleOddPunches($punches): void
    {
        Log::info("Handling odd punches.");

        foreach ($punches as $punch) {
            $punch->status = 'Partial';
            $punch->issue_notes = 'Odd punch count detected. Manual intervention required.';
            $punch->save();

            Log::info("Marked Punch ID: {$punch->id} as Partial due to odd punch count.");
        }
    }

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
        $this->assignLunchAndBreakPunches($remainingPairs);
    }

    private function assignLunchAndBreakPunches($pairs): void
    {
        Log::info("Assigning lunch and break punches.");

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

    private function getPunchTypeId(string $type): ?int
    {
        $id = \DB::table('punch_types')->where('name', $type)->value('id');
        Log::info("Retrieved Punch Type ID: {$id} for type: {$type}.");
        return $id;
    }
}
