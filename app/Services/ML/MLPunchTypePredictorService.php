<?php

namespace App\Services\ML;

use App\Models\Attendance;
use App\Services\Shift\ShiftScheduleService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Phpml\Classification\KNearestNeighbors;
use Phpml\ModelManager;

class MLPunchTypePredictorService
{
    private KNearestNeighbors $classifier;

    private string $modelPath;

    protected ShiftScheduleService $shiftScheduleService;

    protected array $punchTypeCache = [];

    protected array $punchTypeNameCache = [];

    protected array $punchTypeDirectionCache = [];

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->modelPath = storage_path('ml/punch_model.serialized');
        $this->classifier = new KNearestNeighbors(3);
        $this->shiftScheduleService = $shiftScheduleService;
        $this->cachePunchTypes();
        $this->loadOrTrainModel();
    }

    protected function cachePunchTypes(): void
    {
        $punchTypes = DB::table('punch_types')->get();
        foreach ($punchTypes as $punchType) {
            $this->punchTypeCache[$punchType->name] = $punchType->id;
            $this->punchTypeNameCache[$punchType->id] = $punchType->name;
            $this->punchTypeDirectionCache[$punchType->id] = $punchType->punch_direction;
        }
    }

    private function loadOrTrainModel(): void
    {
        $modelManager = new ModelManager;

        if (file_exists($this->modelPath)) {
            $model = $modelManager->restoreFromFile($this->modelPath);

            if ($model instanceof KNearestNeighbors) {
                $this->classifier = $model;
            } else {
                $this->trainModel();
            }
        } else {
            $this->trainModel();
        }
    }

    public function trainModel(): void
    {
        DB::disableQueryLog();

        if (! File::exists(storage_path('ml'))) {
            File::makeDirectory(storage_path('ml'), 0755, true);
        }

        // Limit training data to prevent memory exhaustion
        $maxTrainingRecords = 1000;
        $samples = [];
        $labels = [];

        // Use cursor to avoid loading all records into memory
        $query = Attendance::whereNotNull('punch_type_id')
            ->where('status', 'Complete')
            ->orderBy('punch_time', 'desc')
            ->limit($maxTrainingRecords);

        foreach ($query->cursor() as $record) {
            $timeValue = strtotime($record->punch_time) % 86400;
            $shiftDateValue = $record->shift_date ? strtotime($record->shift_date) % 86400 : 0;

            $samples[] = [$record->employee_id, $shiftDateValue, $timeValue];
            $labels[] = $record->punch_type_id;
        }

        if (empty($samples) || empty($labels)) {
            return;
        }

        $this->classifier->train($samples, $labels);

        $modelManager = new ModelManager;
        $modelManager->saveToFile($this->classifier, $this->modelPath);

        Log::info('[ML] Model trained with '.count($samples).' records.');
    }

    public function assignPunchTypes($punches, int $employeeId, &$punchEvaluations): void
    {
        DB::disableQueryLog();

        if ($this->isTrainingDataInsufficient()) {
            return;
        }

        $groupedPunches = $punches->groupBy(['employee_id', 'shift_date']);

        foreach ($groupedPunches as $empId => $days) {
            foreach ($days as $shiftDate => $dayPunches) {
                $sortedPunches = $dayPunches->sortBy('punch_time')->values();

                $shiftSchedule = $this->shiftScheduleService->getShiftScheduleForEmployee($empId);
                if (! $shiftSchedule) {
                    $this->assignIndividualPredictions($sortedPunches, $empId, $punchEvaluations);

                    continue;
                }

                $lunchStart = Carbon::parse($shiftSchedule->lunch_start_time);
                $lunchStop = Carbon::parse($shiftSchedule->lunch_stop_time);

                $assignedTypes = $this->determinePunchTypesML($sortedPunches, $lunchStart, $lunchStop, $shiftSchedule, $empId);

                foreach ($sortedPunches as $index => $punch) {
                    $predictedTypeId = $assignedTypes[$index] ?? null;

                    if ($predictedTypeId) {
                        $punchEvaluations[$punch->id]['ml'] = [
                            'punch_type_id' => $predictedTypeId,
                            'punch_state' => $this->determinePunchState($predictedTypeId),
                            'source' => 'ML Engine',
                        ];
                    }
                }
            }
        }
    }

    private function isTrainingDataInsufficient(): bool
    {
        static $cachedCount = null;

        if ($cachedCount === null) {
            $cachedCount = DB::table('attendances')
                ->whereNotNull('punch_type_id')
                ->where('status', 'Complete')
                ->count();
        }

        return $cachedCount < 50;
    }

    public function predictPunchType(int $employeeId, string $punchTime, ?string $shiftDate = null, ?string $externalGroupId = null): ?int
    {
        $timeValue = strtotime($punchTime) % 86400;
        $shiftDateValue = $shiftDate ? strtotime($shiftDate) % 86400 : 0;

        try {
            $inputData = [$employeeId, $shiftDateValue, $timeValue];
            $predicted = $this->classifier->predict($inputData);

            return is_numeric($predicted) ? (int) $predicted : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get punch state from cached punch_direction (database-driven).
     */
    private function determinePunchState(int $punchTypeId): string
    {
        return $this->punchTypeDirectionCache[$punchTypeId] ?? 'unknown';
    }

    /**
     * Determine punch types using ML predictions combined with pattern logic
     */
    private function determinePunchTypesML($punches, $lunchStart, $lunchStop, $shiftSchedule, $employeeId): array
    {
        $punchCount = count($punches);
        $assignedTypes = array_fill(0, $punchCount, null);

        if ($punchCount === 1) {
            return [null];
        }

        if ($punchCount === 2) {
            return [$this->getPunchTypeId('Clock In'), $this->getPunchTypeId('Clock Out')];
        }

        if ($punchCount === 3) {
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

        if ($punchCount >= 5) {
            $assignedTypes[0] = $this->getPunchTypeId('Clock In');
            $assignedTypes[$punchCount - 1] = $this->getPunchTypeId('Clock Out');

            if ($punchCount >= 6) {
                $innerPunches = $punches->slice(1, -1);
                $bestLunchPair = $this->findBestLunchPairML($innerPunches, $lunchStart, $lunchStop, $shiftSchedule, $employeeId);

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

                    $this->assignBreakPunchTypesML($remainingPunches, $assignedTypes, $punches, $employeeId);
                } else {
                    $this->assignBreakPunchTypesML($innerPunches, $assignedTypes, $punches, $employeeId);
                }
            } else {
                $innerPunches = $punches->slice(1, -1);
                $this->assignBreakPunchTypesML($innerPunches, $assignedTypes, $punches, $employeeId);
            }
        }

        return $assignedTypes;
    }

    /**
     * Find the best lunch pair using ML predictions + timing logic
     */
    private function findBestLunchPairML($innerPunches, $lunchStart, $lunchStop, $shiftSchedule, $employeeId): ?array
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

                $score = $this->scoreLunchPairML($startPunch, $stopPunch, $lunchStart, $lunchStop, $expectedDuration, $flexibility, $employeeId);

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
     * Score a lunch pair using ML predictions + schedule logic
     */
    private function scoreLunchPairML($startPunch, $stopPunch, $lunchStart, $lunchEnd, $expectedDuration, $flexibility, $employeeId): int
    {
        $score = 0;

        $startPrediction = $this->predictPunchType($employeeId, $startPunch->punch_time, $startPunch->shift_date);
        $stopPrediction = $this->predictPunchType($employeeId, $stopPunch->punch_time, $stopPunch->shift_date);

        $lunchStartId = $this->getPunchTypeId('Lunch Start');
        $lunchStopId = $this->getPunchTypeId('Lunch Stop');

        if ($startPrediction == $lunchStartId && $stopPrediction == $lunchStopId) {
            $score += 20;
        } elseif ($startPrediction == $lunchStartId || $stopPrediction == $lunchStopId) {
            $score += 10;
        }

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

        return $score;
    }

    /**
     * Assign break punch types
     */
    private function assignBreakPunchTypesML($punches, &$assignedTypes, $originalPunches, $employeeId): void
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
     * Fallback method for individual predictions when no shift schedule
     */
    private function assignIndividualPredictions($punches, $employeeId, &$punchEvaluations): void
    {
        foreach ($punches as $punch) {
            $predictedTypeId = $this->predictPunchType($employeeId, $punch->punch_time, $punch->shift_date, $punch->external_group_id);

            if ($predictedTypeId) {
                $punchEvaluations[$punch->id]['ml'] = [
                    'punch_type_id' => $predictedTypeId,
                    'punch_state' => $this->determinePunchState($predictedTypeId),
                    'source' => 'ML Engine (Fallback)',
                ];
            }
        }
    }

    private function getPunchTypeId(string $type): ?int
    {
        return $this->punchTypeCache[$type] ?? null;
    }
}
