<?php

namespace App\Services\Heuristic;

use DB;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
use App\Services\Shift\ShiftScheduleService;
use Carbon\Carbon;

class HeuristicPunchTypeAssignmentService
{
    protected ShiftScheduleService $shiftScheduleService;

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
        Log::info("[Heuristic] Initialized HeuristicPunchTypeAssignmentService.");
    }

    public function assignPunchTypes($punches, $flexibility, &$punchEvaluations): void
    {
        Log::info("[Heuristic] Processing Punch Assignments...");

        $groupedPunches = $punches->groupBy(['employee_id', 'shift_date']);

        foreach ($groupedPunches as $employeeId => $days) {
            foreach ($days as $shiftDate => $dayPunches) {
                $sortedPunches = $dayPunches->sortBy('punch_time')->values();

                // Fetch shift schedule
                $shiftSchedule = $this->shiftScheduleService->getShiftScheduleForEmployee($employeeId);
                if (!$shiftSchedule) {
                    Log::warning("[Heuristic] No Shift Schedule Found for Employee: {$employeeId}");
                    continue;
                }

                $lunchStart = Carbon::parse($shiftSchedule->lunch_start);
                $lunchStop = Carbon::parse($shiftSchedule->lunch_stop);

                // Assign Punch Types
                $assignedTypes = $this->determinePunchTypes($sortedPunches, $lunchStart, $lunchStop);

                foreach ($sortedPunches as $index => $punch) {
                    $punchEvaluations[$punch->id]['heuristic'] = [
                        'punch_type_id' => $assignedTypes[$index] ?? null,
                        'punch_state' => $this->determinePunchState($assignedTypes[$index] ?? null),
                    ];

                    Log::info("✅ [Heuristic] Assigned Punch ID: {$punch->id} -> Type: " . ($assignedTypes[$index] ?? 'NULL'));
                }
            }
        }
    }

    private function determinePunchTypes($punches, $lunchStart, $lunchStop): array
    {
        $punchCount = count($punches);
        $assignedTypes = array_fill(0, $punchCount, null);

        if ($punchCount === 1) {
            return [null]; // Needs Review
        }
        if ($punchCount === 2) {
            return [$this->getPunchTypeId('Clock In'), $this->getPunchTypeId('Clock Out')];
        }
        if ($punchCount === 4) {
            return [
                $this->getPunchTypeId('Clock In'),
                $this->getPunchTypeId('Lunch Start'),
                $this->getPunchTypeId('Lunch Stop'),
                $this->getPunchTypeId('Clock Out'),
            ];
        }

        // Assign Clock In / Clock Out
        $assignedTypes[0] = $this->getPunchTypeId('Clock In');
        $assignedTypes[$punchCount - 1] = $this->getPunchTypeId('Clock Out');

        // Handle 6+ Punches (Breaks + Lunch)
        $innerPunches = $punches->slice(1, -1);
        $unassigned = [];

        foreach ($innerPunches as $index => $punch) {
            $punchTime = Carbon::parse($punch->punch_time);

            // Lunch Assignment Logic
            if ($punchTime->between($lunchStart, $lunchStop)) {
                if (!in_array($this->getPunchTypeId('Lunch Start'), $assignedTypes)) {
                    $assignedTypes[$index + 1] = $this->getPunchTypeId('Lunch Start');
                } elseif (!in_array($this->getPunchTypeId('Lunch Stop'), $assignedTypes)) {
                    $assignedTypes[$index + 1] = $this->getPunchTypeId('Lunch Stop');
                } else {
                    $unassigned[] = $index + 1;
                }
            } else {
                $unassigned[] = $index + 1;
            }
        }

        // Assign Breaks if 6+ Punches
        if ($punchCount >= 6 && count($unassigned) >= 2) {
            for ($i = 0; $i < count($unassigned); $i += 2) {
                $assignedTypes[$unassigned[$i]] = $this->getPunchTypeId('Break Start');
                if (isset($unassigned[$i + 1])) {
                    $assignedTypes[$unassigned[$i + 1]] = $this->getPunchTypeId('Break Stop');
                }
            }
        }

        return $assignedTypes;
    }

    private function determinePunchState(?int $punchTypeId): string
    {
        if (!$punchTypeId) return 'NeedsReview';

        $startTypes = ['Clock In', 'Lunch Start', 'Break Start'];
        $stopTypes = ['Clock Out', 'Lunch Stop', 'Break Stop'];

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
        $id = DB::table('punch_types')->where('name', $type)->value('id');

        if (!$id) {
            Log::warning("⚠️ [Heuristic] Punch Type '{$type}' not found.");
        }

        return $id;
    }
}
