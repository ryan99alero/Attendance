<?php

namespace App\Services\Heuristic;

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

                $lunchStart = Carbon::parse($shiftSchedule->lunch_start_time);
                $lunchStop = Carbon::parse($shiftSchedule->lunch_stop_time);

                // Assign Punch Types
                $assignedTypes = $this->determinePunchTypes($sortedPunches, $lunchStart, $lunchStop);

                foreach ($sortedPunches as $index => $punch) {
                    $punchEvaluations[$punch->id]['heuristic'] = [
                        'punch_type_id' => $assignedTypes[$index] ?? null,
                        'punch_state' => $this->determinePunchState($assignedTypes[$index] ?? null),
                        'source' => 'Heuristic Engine'
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
        if ($punchCount === 3) {
            // 3 punches: Clock In, Break/Lunch, Clock Out - assign middle as break
            return [
                $this->getPunchTypeId('Clock In'),
                $this->getPunchTypeId('Break Start'),
                $this->getPunchTypeId('Clock Out')
            ];
        }
        if ($punchCount === 4) {
            return [
                $this->getPunchTypeId('Clock In'),
                $this->getPunchTypeId('Lunch Start'),
                $this->getPunchTypeId('Lunch Stop'),
                $this->getPunchTypeId('Clock Out'),
            ];
        }
        if ($punchCount === 5) {
            // 5 punches: Clock In, Break Start, Break Stop, Lunch/Break, Clock Out
            return [
                $this->getPunchTypeId('Clock In'),
                $this->getPunchTypeId('Break Start'),
                $this->getPunchTypeId('Break End'),
                $this->getPunchTypeId('Lunch Start'),
                $this->getPunchTypeId('Clock Out')
            ];
        }

        // Assign Clock In / Clock Out
        $assignedTypes[0] = $this->getPunchTypeId('Clock In');
        $assignedTypes[$punchCount - 1] = $this->getPunchTypeId('Clock Out');

        // Handle 6+ Punches (Breaks + Lunch) - Using same logic as ShiftSchedule
        if ($punchCount >= 6) {
            $innerPunches = $punches->slice(1, -1);

            // Find best lunch pair based on timing and schedule
            $bestLunchPair = $this->findBestLunchPair($innerPunches, $lunchStart, $lunchStop);

            if ($bestLunchPair) {
                Log::info("[Heuristic] Found best lunch pair - Start: {$bestLunchPair['start']->id}, Stop: {$bestLunchPair['stop']->id}");

                // Find the indices of the lunch punches in the original array
                $startIndex = $punches->search(function($punch) use ($bestLunchPair) {
                    return $punch->id === $bestLunchPair['start']->id;
                });
                $stopIndex = $punches->search(function($punch) use ($bestLunchPair) {
                    return $punch->id === $bestLunchPair['stop']->id;
                });

                $assignedTypes[$startIndex] = $this->getPunchTypeId('Lunch Start');
                $assignedTypes[$stopIndex] = $this->getPunchTypeId('Lunch Stop');

                // Remove lunch punches from the list and assign remaining as breaks
                $remainingPunches = $innerPunches->reject(function ($punch) use ($bestLunchPair) {
                    return $punch->id === $bestLunchPair['start']->id || $punch->id === $bestLunchPair['stop']->id;
                })->values();

                $this->assignBreakPunchTypes($remainingPunches, $assignedTypes, $punches);
            } else {
                Log::info("[Heuristic] No optimal lunch pair found, assigning all middle punches as breaks");
                $this->assignBreakPunchTypes($innerPunches, $assignedTypes, $punches);
            }
        } else {
            // Handle other punch counts (3, 5, 7, etc.) that fall through
            Log::info("[Heuristic] Handling {$punchCount} punches - assigning middle punches as breaks");
            if ($punchCount >= 3) {
                $innerPunches = $punches->slice(1, -1);
                $this->assignBreakPunchTypes($innerPunches, $assignedTypes, $punches);
            }
        }

        return $assignedTypes;
    }

    private function determinePunchState(?int $punchTypeId): string
    {
        if (!$punchTypeId) return 'NeedsReview';

        $startTypes = ['Clock In', 'Lunch Start', 'Break Start'];
        $stopTypes = ['Clock Out', 'Lunch Stop', 'Break End'];

        $punchTypeName = \DB::table('punch_types')->where('id', $punchTypeId)->value('name');

        if (in_array($punchTypeName, $startTypes)) {
            return 'start';
        } elseif (in_array($punchTypeName, $stopTypes)) {
            return 'stop';
        }

        return 'unknown';
    }

    private function getPunchTypeId(string $type): ?int
    {
        $id = \DB::table('punch_types')->where('name', $type)->value('id');

        if (!$id) {
            Log::warning("⚠️ [Heuristic] Punch Type '{$type}' not found.");
        }

        return $id;
    }

    /**
     * Find the best lunch pair from inner punches based on timing and schedule
     */
    private function findBestLunchPair($innerPunches, $lunchStart, $lunchStop): ?array
    {
        $bestPair = null;
        $bestScore = -1;
        $punchCount = $innerPunches->count();

        // Try all possible consecutive pairs
        for ($i = 0; $i < $punchCount - 1; $i += 2) {
            if ($i + 1 >= $punchCount) break;

            $startPunch = $innerPunches->get($i);
            $stopPunch = $innerPunches->get($i + 1);

            if (!$startPunch || !$stopPunch) {
                continue;
            }

            $score = $this->scoreLunchPair($startPunch, $stopPunch, $lunchStart, $lunchStop);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPair = [
                    'start' => $startPunch,
                    'stop' => $stopPunch,
                    'score' => $score
                ];
            }
        }

        return ($bestScore > 0) ? $bestPair : null;
    }

    /**
     * Score a lunch pair based on timing alignment with schedule
     */
    private function scoreLunchPair($startPunch, $stopPunch, $lunchStart, $lunchStop): float
    {
        $startTime = Carbon::parse($startPunch->punch_time);
        $stopTime = Carbon::parse($stopPunch->punch_time);

        $score = 0;

        // Score based on start time proximity to scheduled lunch start (max 50 points)
        $startDiffMinutes = abs($startTime->diffInMinutes($lunchStart));
        $startScore = max(0, 50 - ($startDiffMinutes / 2)); // Lose 0.5 points per minute away
        $score += $startScore;

        // Score based on stop time proximity to scheduled lunch stop (max 50 points)
        $stopDiffMinutes = abs($stopTime->diffInMinutes($lunchStop));
        $stopScore = max(0, 50 - ($stopDiffMinutes / 2)); // Lose 0.5 points per minute away
        $score += $stopScore;

        // Bonus for reasonable lunch duration (15-90 minutes)
        $actualDuration = $startTime->diffInMinutes($stopTime);
        if ($actualDuration >= 15 && $actualDuration <= 90) {
            $score += 20;
        }

        Log::info("[Heuristic] Lunch pair score: Start {$startPunch->id} -> Stop {$stopPunch->id} = {$score} (duration: {$actualDuration}min)");

        return $score;
    }

    /**
     * Assign break punch types similar to Shift Schedule approach
     */
    private function assignBreakPunchTypes($punches, &$assignedTypes, $originalPunches): void
    {
        Log::info("[Heuristic] Assigning {$punches->count()} punches as breaks");

        $punchCount = $punches->count();

        // Assign remaining punches as Break Start/Break Stop pairs chronologically
        for ($i = 0; $i < $punchCount; $i += 2) {
            if ($i >= $punchCount) break;

            $startPunch = $punches->get($i);
            if (!$startPunch) {
                Log::warning("[Heuristic] Null startPunch at index {$i}, skipping");
                continue;
            }

            $startIndex = $originalPunches->search(function($punch) use ($startPunch) {
                return $punch && $punch->id === $startPunch->id;
            });

            if ($startIndex !== false) {
                $assignedTypes[$startIndex] = $this->getPunchTypeId('Break Start');
                Log::info("[Heuristic] Assigned Break Start to Punch ID: {$startPunch->id}");
            }

            if ($i + 1 < $punchCount) {
                $endPunch = $punches->get($i + 1);
                if (!$endPunch) {
                    Log::warning("[Heuristic] Null endPunch at index " . ($i + 1) . ", skipping");
                    continue;
                }

                $endIndex = $originalPunches->search(function($punch) use ($endPunch) {
                    return $punch && $punch->id === $endPunch->id;
                });

                if ($endIndex !== false) {
                    $assignedTypes[$endIndex] = $this->getPunchTypeId('Break End');
                    Log::info("[Heuristic] Assigned Break End to Punch ID: {$endPunch->id}");
                }
            }
        }
    }
}
