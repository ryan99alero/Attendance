<?php

namespace App\Services\Shift;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShiftSchedulePunchTypeAssignmentService
{
    protected ShiftScheduleService $shiftScheduleService;

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
        Log::info("🛠 Initialized ShiftSchedulePunchTypeAssignmentService.");
    }

    public function assignPunchTypes($punches, $flexibility): void
    {
        foreach ($punches->groupBy('employee_id') as $employeeId => $employeePunches) {
            $schedule = $this->shiftScheduleService->getShiftScheduleForEmployee($employeeId);

            if (!$schedule) {
                Log::warning("❌ No shift schedule found for Employee ID: {$employeeId}");
                foreach ($employeePunches as $punch) {
                    $punch->status = 'NeedsReview';
                    $punch->issue_notes = 'No shift schedule found';
                    $punch->save();
                }
                continue;
            }

            Log::info("✅ Using Shift Schedule ID: {$schedule->id} for Employee ID: {$employeeId}");

            // Process Punch Types
            $this->processPunchAssignments($employeePunches, $schedule, $flexibility);
        }
    }

    private function processPunchAssignments($punches, $schedule, $flexibility): void
    {
        $punchesByDay = $punches->groupBy(fn($punch) => Carbon::parse($punch->punch_time)->format('Y-m-d'));

        foreach ($punchesByDay as $day => $dailyPunches) {
            Log::info("🔍 Processing punches for Date: {$day}, Employee ID: {$dailyPunches->first()->employee_id}, Count: " . $dailyPunches->count());

            $dailyPunches = $dailyPunches->sortBy('punch_time')->values();
            if ($dailyPunches->count() % 2 !== 0) {
                Log::warning("⚠️ Odd punch count detected for Employee ID: {$dailyPunches->first()->employee_id} on Date: {$day}");
                foreach ($dailyPunches as $punch) {
                    $punch->status = 'NeedsReview';
                    $punch->issue_notes = 'Odd punch count detected';
                    $punch->save();
                }
                continue;
            }

            $this->assignScheduledPunchTypes($dailyPunches, $schedule, $flexibility);
        }
    }

    private function assignScheduledPunchTypes($punches, $schedule, $flexibility): void
    {
        $shiftStart = Carbon::parse($schedule->start_time);
        $shiftEnd = Carbon::parse($schedule->end_time);
        $lunchStart = Carbon::parse($schedule->lunch_start_time);
        $lunchEnd = Carbon::parse($schedule->lunch_stop_time);

        $firstPunch = $punches->first();
        $lastPunch = $punches->last();

        // Assign Clock In
        if ($this->isWithinFlexibility($firstPunch->punch_time, $shiftStart, $flexibility)) {
            $this->assignPunchType($firstPunch, 'Clock In');
        }

        // Assign Clock Out
        if ($this->isWithinFlexibility($lastPunch->punch_time, $shiftEnd, $flexibility)) {
            $this->assignPunchType($lastPunch, 'Clock Out');
        }

        Log::info("✅ Assigned Clock In (Punch ID: {$firstPunch->id}) and Clock Out (Punch ID: {$lastPunch->id}).");

        // Assign Lunch Start and Lunch Stop
        $remainingPunches = $punches->slice(1, -1);
        $this->assignLunchPunchTypes($remainingPunches, $lunchStart, $lunchEnd, $schedule);
    }

    private function assignLunchPunchTypes($punches, $lunchStart, $lunchEnd, $schedule): void
    {
        foreach ($punches->chunk(2) as $pair) {
            if ($pair->count() !== 2) {
                continue;
            }

            $first = $pair->first();
            $second = $pair->last();

            if ($this->isWithinFlexibility($first->punch_time, $lunchStart, 10)) {
                Log::info("✅ Assigning Lunch Start (Punch ID: {$first->id}) for Shift ID: {$schedule->id}");
                $this->assignPunchType($first, 'Lunch Start');
            } else {
                Log::warning("❌ First Punch ID: {$first->id} is NOT within lunch start window.");
            }

            if ($this->isWithinFlexibility($second->punch_time, $lunchEnd, 10)) {
                Log::info("✅ Assigning Lunch Stop (Punch ID: {$second->id}) for Shift ID: {$schedule->id}");
                $this->assignPunchType($second, 'Lunch Stop');
            } else {
                Log::warning("❌ Second Punch ID: {$second->id} is NOT within lunch stop window.");
            }
        }
    }

    private function isWithinFlexibility($punchTime, $scheduledTime, $flexibility): bool
    {
        return Carbon::parse($punchTime)->between($scheduledTime->copy()->subMinutes($flexibility), $scheduledTime->copy()->addMinutes($flexibility));
    }

    private function assignPunchType($punch, $type): void
    {
        $punchTypeId = $this->getPunchTypeId($type);
        if (!$punchTypeId) {
            Log::warning("⚠️ Punch Type ID not found for: {$type}, Punch ID: {$punch->id}");
            return;
        }

        Log::info("🛠 Assigning {$type} (ID: {$punchTypeId}) to Punch ID: {$punch->id}");

        $punch->punch_type_id = $punchTypeId;
        $punch->status = 'Complete';
        $punch->issue_notes = "Assigned {$type}";

        if ($punch->save()) {
            Log::info("✅ Successfully saved Punch ID: {$punch->id} with Punch Type ID: {$punchTypeId}");
        } else {
            Log::error("❌ Failed to save Punch ID: {$punch->id} with Punch Type ID: {$punchTypeId}");
        }
    }

    private function getPunchTypeId(string $type): ?int
    {
        return \DB::table('punch_types')->where('name', $type)->value('id');
    }
}
