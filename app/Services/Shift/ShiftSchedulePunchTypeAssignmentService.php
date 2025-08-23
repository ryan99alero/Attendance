<?php

namespace App\Services\Shift;

use DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShiftSchedulePunchTypeAssignmentService
{
    protected ShiftScheduleService $shiftScheduleService;

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
        Log::info("[Shift] Initialized ShiftSchedulePunchTypeAssignmentService.");
    }

    public function assignPunchTypes($punches, $flexibility, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering assignPunchTypes");
        foreach ($punches->groupBy('employee_id') as $employeeId => $employeePunches) {
            $schedule = $this->shiftScheduleService->getShiftScheduleForEmployee($employeeId);

            if (!$schedule) {
                Log::warning("❌ [Shift] No shift schedule found for Employee ID: {$employeeId}");
                foreach ($employeePunches as $punch) {
                    $punchEvaluations[$punch->id]['shift'] = [
                        'punch_type_id' => null,
                        'punch_state' => 'unknown',
                        'source' => 'Shift Schedule (No Match)'
                    ];
                }
                continue;
            }

            Log::info("✅ [Shift] Using Shift Schedule ID: {$schedule->id} for Employee ID: {$employeeId}");

            // Process Punch Assignments
            $this->processPunchAssignments($employeePunches, $schedule, $flexibility, $punchEvaluations);
        }
    }

    private function processPunchAssignments($punches, $schedule, $flexibility, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering processPunchAssignments");
        $punchesByDay = $punches->groupBy(fn($punch) => Carbon::parse($punch->punch_time)->format('Y-m-d'));

        foreach ($punchesByDay as $day => $dailyPunches) {
            Log::info("🔍 [Shift] Processing punches for Date: {$day}, Employee ID: {$dailyPunches->first()->employee_id}, Count: " . $dailyPunches->count());

            $dailyPunches = $dailyPunches->sortBy('punch_time')->values();

            // Store predictions for later review, even if punch count is odd
            $this->assignScheduledPunchTypes($dailyPunches, $schedule, $flexibility, $punchEvaluations);
        }
    }

    private function assignScheduledPunchTypes($punches, $schedule, $flexibility, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering assignScheduledPunchTypes");
        $shiftStart = Carbon::parse($schedule->start_time);
        $shiftEnd = Carbon::parse($schedule->end_time);
        $lunchStart = Carbon::parse($schedule->lunch_start_time);
        $lunchEnd = Carbon::parse($schedule->lunch_stop_time);

        $firstPunch = $punches->first();
        $lastPunch = $punches->last();

        // Assign Clock In
        if ($this->isWithinFlexibility($firstPunch->punch_time, $shiftStart, $flexibility)) {
            $this->storePunchPrediction($firstPunch, 'Clock In', $punchEvaluations);
        }

        // Assign Clock Out
        if ($this->isWithinFlexibility($lastPunch->punch_time, $shiftEnd, $flexibility)) {
            $this->storePunchPrediction($lastPunch, 'Clock Out', $punchEvaluations);
        }

        Log::info("✅ [Shift] Predicted Clock In (Punch ID: {$firstPunch->id}) and Clock Out (Punch ID: {$lastPunch->id}).");

        // Assign Lunch Start and Lunch Stop
        $remainingPunches = $punches->slice(1, -1);
        $this->assignLunchPunchTypes($remainingPunches, $lunchStart, $lunchEnd, $schedule, $punchEvaluations);
    }

    private function assignLunchPunchTypes($punches, $lunchStart, $lunchEnd, $schedule, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering assignLunchPunchTypes");
        foreach ($punches->chunk(2) as $pair) {
            if ($pair->count() !== 2) {
                continue;
            }

            $first = $pair->first();
            $second = $pair->last();

            if ($this->isWithinFlexibility($first->punch_time, $lunchStart, 10)) {
                Log::info("✅ [Shift] Assigning Lunch Start (Punch ID: {$first->id}) for Shift ID: {$schedule->id}");
                $this->storePunchPrediction($first, 'Lunch Start', $punchEvaluations);
            }

            if ($this->isWithinFlexibility($second->punch_time, $lunchEnd, 10)) {
                Log::info("✅ [Shift] Assigning Lunch Stop (Punch ID: {$second->id}) for Shift ID: {$schedule->id}");
                $this->storePunchPrediction($second, 'Lunch Stop', $punchEvaluations);
            }
        }
    }

    private function isWithinFlexibility($punchTime, $scheduledTime, $flexibility): bool
    {
        return Carbon::parse($punchTime)->between($scheduledTime->copy()->subMinutes($flexibility), $scheduledTime->copy()->addMinutes($flexibility));
    }

    private function storePunchPrediction($punch, $type, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering storePunchPrediction");
        $punchTypeId = $this->getPunchTypeId($type);

        if (!$punchTypeId) {
            Log::warning("⚠️ [Shift] Punch Type ID not found for: {$type}, Punch ID: {$punch->id}");
            return;
        }

        Log::info("🛠 [Shift] Predicting {$type} (ID: {$punchTypeId}) for Punch ID: {$punch->id}");

        $punchEvaluations[$punch->id]['shift'] = [
            'punch_type_id' => $punchTypeId,
            'punch_state' => $this->determinePunchState($punchTypeId),
            'source' => 'Shift Schedule'
        ];
    }

    private function determinePunchState(int $punchTypeId): string
    {
        Log::info("➡️ [Heuristic] Entering determinePunchState");
        $startTypes = ['Clock In', 'Lunch Start', 'Shift Start', 'Manual Start'];
        $stopTypes = ['Clock Out', 'Lunch Stop', 'Shift Stop', 'Manual Stop'];

        $punchTypeName = DB::table('punch_types')->where('id', $punchTypeId)->value('name');

        if (in_array($punchTypeName, $startTypes)) {
            return 'start';
        } elseif (in_array($punchTypeName, $stopTypes)) {
            return 'stop';
        }

        return 'unknown';
    }

    private function getPunchTypeId(string $type): ?int
    {
        Log::info("➡️ [Heuristic] Entering getPunchTypeId");
        return DB::table('punch_types')->where('name', $type)->value('id');
    }
}
