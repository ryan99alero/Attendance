<?php

namespace App\Services\Heuristic;

use App\Services\Shift\ShiftScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HeuristicPunchTypeAssignmentService
{
    protected ShiftScheduleService $shiftScheduleService;

    protected array $punchTypeCache = [];

    protected array $punchTypeNameCache = [];

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
        $this->cachePunchTypes();
    }

    protected function cachePunchTypes(): void
    {
        $punchTypes = DB::table('punch_types')->get();
        foreach ($punchTypes as $punchType) {
            $this->punchTypeCache[$punchType->name] = $punchType->id;
            $this->punchTypeNameCache[$punchType->id] = $punchType->name;
        }
    }

    public function assignPunchTypes($punches, $flexibility, &$punchEvaluations): void
    {
        DB::disableQueryLog();

        $groupedPunches = $punches->groupBy(['employee_id', 'shift_date']);

        foreach ($groupedPunches as $employeeId => $days) {
            foreach ($days as $shiftDate => $dayPunches) {
                $sortedPunches = $dayPunches->sortBy('punch_time')->values();

                $shiftSchedule = $this->shiftScheduleService->getShiftScheduleForEmployee($employeeId);
                if (! $shiftSchedule) {
                    continue;
                }

                $lunchStart = Carbon::parse($shiftSchedule->lunch_start_time);
                $lunchStop = Carbon::parse($shiftSchedule->lunch_stop_time);

                $assignedTypes = $this->determinePunchTypes($sortedPunches, $lunchStart, $lunchStop, $shiftSchedule);

                foreach ($sortedPunches as $index => $punch) {
                    $punchEvaluations[$punch->id]['heuristic'] = [
                        'punch_type_id' => $assignedTypes[$index] ?? null,
                        'punch_state' => $this->determinePunchState($assignedTypes[$index] ?? null),
                        'source' => 'Heuristic Engine',
                    ];
                }
            }
        }
    }

    private function determinePunchTypes($punches, $lunchStart, $lunchStop, $shiftSchedule): array
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
                $this->getPunchTypeId('Clock Out'),
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
                $this->getPunchTypeId('Clock Out'),
            ];
        }

        // Assign Clock In / Clock Out
        $assignedTypes[0] = $this->getPunchTypeId('Clock In');
        $assignedTypes[$punchCount - 1] = $this->getPunchTypeId('Clock Out');

        // Handle 6+ Punches (Breaks + Lunch) - Using same logic as ShiftSchedule
        if ($punchCount >= 6) {
            $innerPunches = $punches->slice(1, -1);

            // Find best lunch pair based on timing and schedule
            $bestLunchPair = $this->findBestLunchPair($innerPunches, $lunchStart, $lunchStop, $shiftSchedule);

            if ($bestLunchPair) {
                $startIndex = $punches->search(function ($punch) use ($bestLunchPair) {
                    return $punch->id === $bestLunchPair['start']->id;
                });
                $stopIndex = $punches->search(function ($punch) use ($bestLunchPair) {
                    return $punch->id === $bestLunchPair['stop']->id;
                });

                $assignedTypes[$startIndex] = $this->getPunchTypeId('Lunch Start');
                $assignedTypes[$stopIndex] = $this->getPunchTypeId('Lunch Stop');

                $remainingPunches = $innerPunches->reject(function ($punch) use ($bestLunchPair) {
                    return $punch->id === $bestLunchPair['start']->id || $punch->id === $bestLunchPair['stop']->id;
                })->values();

                $this->assignBreakPunchTypes($remainingPunches, $assignedTypes, $punches);
            } else {
                $this->assignBreakPunchTypes($innerPunches, $assignedTypes, $punches);
            }
        } else {
            if ($punchCount >= 3) {
                $innerPunches = $punches->slice(1, -1);
                $this->assignBreakPunchTypes($innerPunches, $assignedTypes, $punches);
            }
        }

        return $assignedTypes;
    }

    private function determinePunchState(?int $punchTypeId): string
    {
        if (! $punchTypeId) {
            return 'NeedsReview';
        }

        $startTypes = ['Clock In', 'Lunch Start', 'Break Start'];
        $stopTypes = ['Clock Out', 'Lunch Stop', 'Break End'];

        $punchTypeName = $this->punchTypeNameCache[$punchTypeId] ?? null;

        if (in_array($punchTypeName, $startTypes)) {
            return 'start';
        } elseif (in_array($punchTypeName, $stopTypes)) {
            return 'stop';
        }

        return 'unknown';
    }

    private function getPunchTypeId(string $type): ?int
    {
        return $this->punchTypeCache[$type] ?? null;
    }

    /**
     * Find the best lunch pair from inner punches based on timing and schedule
     */
    private function findBestLunchPair($innerPunches, $lunchStart, $lunchStop, $shiftSchedule): ?array
    {
        $bestPair = null;
        $bestScore = -1;
        $punchCount = $innerPunches->count();
        $flexibility = 30;
        $expectedDuration = $shiftSchedule->lunch_duration ?? 30;

        for ($i = 0; $i < $punchCount - 1; $i++) {
            for ($j = $i + 1; $j < $punchCount; $j++) {
                $startPunch = $innerPunches->get($i);
                $stopPunch = $innerPunches->get($j);

                if (! $startPunch || ! $stopPunch) {
                    continue;
                }

                $score = $this->scoreLunchPairShiftStyle($startPunch, $stopPunch, $lunchStart, $lunchStop, $expectedDuration, $flexibility);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestPair = [
                        'start' => $startPunch,
                        'stop' => $stopPunch,
                        'score' => $score,
                    ];
                }
            }
        }

        return ($bestScore > 0) ? $bestPair : null;
    }

    /**
     * Score a lunch pair using Shift Schedule logic
     */
    private function scoreLunchPairShiftStyle($startPunch, $stopPunch, $lunchStart, $lunchEnd, $expectedDuration, $flexibility): int
    {
        $score = 0;

        if ($this->isWithinFlexibility($startPunch->punch_time, $lunchStart, $flexibility)) {
            $score += 10;
        }

        if ($this->isWithinFlexibility($stopPunch->punch_time, $lunchEnd, $flexibility)) {
            $score += 10;
        }

        $actualDuration = Carbon::parse($startPunch->punch_time)->diffInMinutes(Carbon::parse($stopPunch->punch_time));
        $durationDiff = abs($actualDuration - $expectedDuration);

        if ($durationDiff <= 5) {
            $score += 15;
        } elseif ($durationDiff <= 15) {
            $score += 10;
        } elseif ($durationDiff <= 30) {
            $score += 5;
        } elseif ($actualDuration >= 15 && $actualDuration <= 120) {
            $score += 2;
        }

        $startHour = Carbon::parse($startPunch->punch_time)->hour;
        if ($startHour >= 11 && $startHour <= 14) {
            $score += 5;
        } elseif ($startHour >= 10 && $startHour <= 15) {
            $score += 2;
        }

        if ($actualDuration >= 10 && $actualDuration <= 180 && $score < 5) {
            $score += 1;
        }

        return $score;
    }

    /**
     * Check if punch time is within flexibility range of scheduled time
     */
    private function isWithinFlexibility($punchTime, $scheduledTime, $flexibility): bool
    {
        $punchTimeOnly = Carbon::parse($punchTime)->format('H:i:s');
        $scheduledTimeOnly = Carbon::parse($scheduledTime)->format('H:i:s');

        $punchCarbon = Carbon::parse($punchTimeOnly);
        $scheduledCarbon = Carbon::parse($scheduledTimeOnly);

        return $punchCarbon->between(
            $scheduledCarbon->copy()->subMinutes($flexibility),
            $scheduledCarbon->copy()->addMinutes($flexibility)
        );
    }

    /**
     * Assign break punch types
     */
    private function assignBreakPunchTypes($punches, &$assignedTypes, $originalPunches): void
    {
        $punchCount = $punches->count();

        for ($i = 0; $i < $punchCount; $i += 2) {
            if ($i >= $punchCount) {
                break;
            }

            $startPunch = $punches->get($i);
            if (! $startPunch) {
                continue;
            }

            $startIndex = $originalPunches->search(function ($punch) use ($startPunch) {
                return $punch && $punch->id === $startPunch->id;
            });

            if ($startIndex !== false) {
                $assignedTypes[$startIndex] = $this->getPunchTypeId('Break Start');
            }

            if ($i + 1 < $punchCount) {
                $endPunch = $punches->get($i + 1);
                if (! $endPunch) {
                    continue;
                }

                $endIndex = $originalPunches->search(function ($punch) use ($endPunch) {
                    return $punch && $punch->id === $endPunch->id;
                });

                if ($endIndex !== false) {
                    $assignedTypes[$endIndex] = $this->getPunchTypeId('Break End');
                }
            }
        }
    }
}
