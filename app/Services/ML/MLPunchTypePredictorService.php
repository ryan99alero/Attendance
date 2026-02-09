<?php

namespace App\Services\ML;

use Exception;
use App\Models\Attendance;
use Phpml\Classification\KNearestNeighbors;
use Phpml\ModelManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\Shift\ShiftScheduleService;

class MLPunchTypePredictorService
{
    private KNearestNeighbors $classifier;
    private string $modelPath;
    protected ShiftScheduleService $shiftScheduleService;

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        Log::info("[ML] Initializing MLPunchTypePredictorService...");

        $this->modelPath = storage_path('ml/punch_model.serialized');
        $this->classifier = new KNearestNeighbors(3); // Default classifier with k=3
        $this->shiftScheduleService = $shiftScheduleService;

        $this->loadOrTrainModel();
    }

    private function loadOrTrainModel(): void
    {
        $modelManager = new ModelManager();

        if (file_exists($this->modelPath)) {
            Log::info("[ML] Model file found. Attempting to restore...");

            $model = $modelManager->restoreFromFile($this->modelPath);

            if ($model instanceof KNearestNeighbors) {
                $this->classifier = $model;
                Log::info("[ML] Model successfully restored.");
            } else {
                Log::warning("[ML] Invalid model detected. Retraining...");
                $this->trainModel();
            }
        } else {
            Log::warning("[ML] No saved model found. Training a new one...");
            $this->trainModel();
        }
    }

    public function trainModel(): void
    {
        Log::info("[ML] Training model...");

        if (!File::exists(storage_path('ml'))) {
            File::makeDirectory(storage_path('ml'), 0755, true);
            Log::info("[ML] Created missing ML model directory.");
        }

        $punchData = Attendance::whereNotNull('punch_type_id')
            ->where('status', 'Complete')
            ->orderBy('punch_time', 'asc')
            ->get();

        Log::info("[ML] Training on " . $punchData->count() . " punch records.");

        if ($punchData->isEmpty()) {
            Log::warning("[ML] Insufficient data for training.");
            return;
        }

        $samples = [];
        $labels = [];

        foreach ($punchData as $record) {
            $timeValue = strtotime($record->punch_time) % 86400;
            $shiftDateValue = $record->shift_date ? strtotime($record->shift_date) % 86400 : 0;

            $samples[] = [$record->employee_id, $shiftDateValue, $timeValue];
            $labels[] = $record->punch_type_id;
        }

        if (empty($samples) || empty($labels)) {
            Log::warning("[ML] No valid samples found for training.");
            return;
        }

        $this->classifier->train($samples, $labels);

        $modelManager = new ModelManager();
        $modelManager->saveToFile($this->classifier, $this->modelPath);

        Log::info("[ML] Model trained and saved.");
    }

    public function assignPunchTypes($punches, int $employeeId, &$punchEvaluations): void
    {
        // ✅ Skip ML if no training data exists
        if ($this->isTrainingDataInsufficient()) {
            Log::warning("⚠️ [ML] Skipping ML Processing for Employee ID: {$employeeId} due to insufficient training data.");
            return;
        }

        Log::info("🤖 [ML] Processing Punch Assignments...");

        $groupedPunches = $punches->groupBy(['employee_id', 'shift_date']);

        foreach ($groupedPunches as $employeeId => $days) {
            foreach ($days as $shiftDate => $dayPunches) {
                $sortedPunches = $dayPunches->sortBy('punch_time')->values();

                Log::info("🤖 [ML] Processing {$sortedPunches->count()} punches for Employee {$employeeId} on {$shiftDate}");

                // Fetch shift schedule for lunch/break detection
                $shiftSchedule = $this->shiftScheduleService->getShiftScheduleForEmployee($employeeId);
                if (!$shiftSchedule) {
                    Log::warning("🤖 [ML] No Shift Schedule Found for Employee: {$employeeId}");
                    // Fall back to individual predictions without context
                    $this->assignIndividualPredictions($sortedPunches, $employeeId, $punchEvaluations);
                    continue;
                }

                $lunchStart = Carbon::parse($shiftSchedule->lunch_start_time);
                $lunchStop = Carbon::parse($shiftSchedule->lunch_stop_time);

                // Assign Punch Types using ML + pattern logic
                $assignedTypes = $this->determinePunchTypesML($sortedPunches, $lunchStart, $lunchStop, $shiftSchedule, $employeeId);

                foreach ($sortedPunches as $index => $punch) {
                    $predictedTypeId = $assignedTypes[$index] ?? null;

                    if ($predictedTypeId) {
                        $punchEvaluations[$punch->id]['ml'] = [
                            'punch_type_id' => $predictedTypeId,
                            'punch_state' => $this->determinePunchState($predictedTypeId),
                            'source' => 'ML Engine'
                        ];
                        Log::info("🤖 [ML] Assigned Punch ID: {$punch->id} -> Type: {$predictedTypeId}");
                    } else {
                        Log::warning("🤖 [ML] No confident prediction for Punch ID: {$punch->id}");
                    }
                }
            }
        }
    }

// ✅ New Function: Check if Training Data Exists
    private function isTrainingDataInsufficient(): bool
    {
        $trainingDataCount = \DB::table('attendances')
            ->whereNotNull('punch_type_id')
            ->where('status', 'Complete')
            ->count();

        Log::info("[ML] Found {$trainingDataCount} training records in attendances table");

        if ($trainingDataCount < 50) {
            Log::info("[ML] Insufficient training data. Need at least 50 complete records, have {$trainingDataCount}");
            Log::info("[ML] Suggestion: Run processing in 'heuristic' or 'shift_schedule' mode first to build training data");
        }

        return $trainingDataCount < 50; // Need at least 50 records for meaningful ML
    }

    public function predictPunchType(int $employeeId, string $punchTime, ?string $shiftDate = null, ?string $externalGroupId = null): ?int
    {
        $timeValue = strtotime($punchTime) % 86400;
        $shiftDateValue = $shiftDate ? strtotime($shiftDate) % 86400 : 0;

        try {
            if (empty($this->classifier)) {
                Log::warning("[ML] Classifier is uninitialized. Training now...");
                $this->trainModel();
            }

            $inputData = [$employeeId, $shiftDateValue, $timeValue];
            $predicted = $this->classifier->predict($inputData);

            Log::info("🤖 [ML] Prediction: Employee ID: {$employeeId}, Punch Time: {$punchTime}, Shift Date: {$shiftDate}, Group: {$externalGroupId} -> Predicted Type ID: " . ($predicted ?? 'NULL'));

            return is_numeric($predicted) ? (int) $predicted : null;
        } catch (Exception $e) {
            Log::error("[ML] Prediction failed. Error: " . $e->getMessage());
            return null;
        }
    }

    private function determinePunchState(int $punchTypeId): string
    {
        $startTypes = ['Clock In', 'Lunch Start', 'Break Start', 'Shift Start', 'Manual Start'];
        $stopTypes = ['Clock Out', 'Lunch Stop', 'Break End', 'Shift Stop', 'Manual Stop'];

        $punchTypeName = DB::table('punch_types')->where('id', $punchTypeId)->value('name');

        if (in_array($punchTypeName, $startTypes)) {
            return 'start';
        } elseif (in_array($punchTypeName, $stopTypes)) {
            return 'stop';
        }

        return 'unknown';
    }

    /**
     * Determine punch types using ML predictions combined with pattern logic for 6+ punches
     */
    private function determinePunchTypesML($punches, $lunchStart, $lunchStop, $shiftSchedule, $employeeId): array
    {
        $punchCount = count($punches);
        $assignedTypes = array_fill(0, $punchCount, null);

        Log::info("🤖 [ML] Determining types for {$punchCount} punches with ML + pattern logic");

        if ($punchCount === 1) {
            return [null]; // Needs Review
        }

        if ($punchCount === 2) {
            return [$this->getPunchTypeId('Clock In'), $this->getPunchTypeId('Clock Out')];
        }

        if ($punchCount === 3) {
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

        // For 5+ punches, use ML + pattern logic
        if ($punchCount >= 5) {
            // Always assign Clock In / Clock Out
            $assignedTypes[0] = $this->getPunchTypeId('Clock In');
            $assignedTypes[$punchCount - 1] = $this->getPunchTypeId('Clock Out');

            if ($punchCount >= 6) {
                $innerPunches = $punches->slice(1, -1);

                // Find best lunch pair using ML + schedule logic
                $bestLunchPair = $this->findBestLunchPairML($innerPunches, $lunchStart, $lunchStop, $shiftSchedule, $employeeId);

                if ($bestLunchPair) {
                    Log::info("🤖 [ML] Found best lunch pair - Start: {$bestLunchPair['start']->id}, Stop: {$bestLunchPair['stop']->id}");

                    // Find the indices of the lunch punches in the original array
                    $startIndex = $punches->search(function($punch) use ($bestLunchPair) {
                        return $punch->id === $bestLunchPair['start']->id;
                    });
                    $stopIndex = $punches->search(function($punch) use ($bestLunchPair) {
                        return $punch->id === $bestLunchPair['stop']->id;
                    });

                    $assignedTypes[$startIndex] = $this->getPunchTypeId('Lunch Start');
                    $assignedTypes[$stopIndex] = $this->getPunchTypeId('Lunch Stop');

                    // Assign remaining punches as breaks
                    $remainingPunches = $innerPunches->reject(function ($punch) use ($bestLunchPair) {
                        return $punch->id === $bestLunchPair['start']->id || $punch->id === $bestLunchPair['stop']->id;
                    })->values();

                    $this->assignBreakPunchTypesML($remainingPunches, $assignedTypes, $punches, $employeeId);
                } else {
                    Log::info("🤖 [ML] No optimal lunch pair found, assigning all middle punches as breaks");
                    $this->assignBreakPunchTypesML($innerPunches, $assignedTypes, $punches, $employeeId);
                }
            } else {
                // Handle 5 punches
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

        Log::info("🤖 [ML] Finding best lunch pair from {$punchCount} punches using ML + schedule logic");

        // Try all possible pairs (not just consecutive)
        for ($i = 0; $i < $punchCount - 1; $i++) {
            for ($j = $i + 1; $j < $punchCount; $j++) {
                $startPunch = $innerPunches->get($i);
                $stopPunch = $innerPunches->get($j);

                if (!$startPunch || !$stopPunch) {
                    continue;
                }

                $score = $this->scoreLunchPairML($startPunch, $stopPunch, $lunchStart, $lunchStop, $expectedDuration, $flexibility, $employeeId);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestPair = [
                        'start' => $startPunch,
                        'stop' => $stopPunch,
                        'score' => $score
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

        Log::info("🤖 [ML] Scoring pair: {$startPunch->punch_time} -> {$stopPunch->punch_time}");

        // ML Prediction Bonus: If ML predicts these as lunch types, boost score
        $startPrediction = $this->predictPunchType($employeeId, $startPunch->punch_time, $startPunch->shift_date);
        $stopPrediction = $this->predictPunchType($employeeId, $stopPunch->punch_time, $stopPunch->shift_date);

        $lunchStartId = $this->getPunchTypeId('Lunch Start');
        $lunchStopId = $this->getPunchTypeId('Lunch Stop');

        if ($startPrediction == $lunchStartId && $stopPrediction == $lunchStopId) {
            $score += 20;
            Log::info("🤖 [ML] ML predictions match lunch pattern (+20)");
        } elseif ($startPrediction == $lunchStartId || $stopPrediction == $lunchStopId) {
            $score += 10;
            Log::info("🤖 [ML] One ML prediction matches lunch (+10)");
        }

        // Schedule-based scoring (same as other engines)
        if ($this->isWithinFlexibility($startPunch->punch_time, $lunchStart, $flexibility)) {
            $score += 10;
            Log::info("🤖 [ML] Start time matches scheduled lunch start (+10)");
        }

        if ($this->isWithinFlexibility($stopPunch->punch_time, $lunchEnd, $flexibility)) {
            $score += 10;
            Log::info("🤖 [ML] End time matches scheduled lunch end (+10)");
        }

        // Duration scoring
        $actualDuration = Carbon::parse($startPunch->punch_time)->diffInMinutes(Carbon::parse($stopPunch->punch_time));
        $durationDiff = abs($actualDuration - $expectedDuration);

        if ($durationDiff <= 5) {
            $score += 15;
            Log::info("🤖 [ML] Perfect duration match: {$actualDuration}min vs expected {$expectedDuration}min (+15)");
        } elseif ($durationDiff <= 15) {
            $score += 10;
            Log::info("🤖 [ML] Good duration match: {$actualDuration}min vs expected {$expectedDuration}min (+10)");
        } elseif ($durationDiff <= 30) {
            $score += 5;
            Log::info("🤖 [ML] Acceptable duration match: {$actualDuration}min vs expected {$expectedDuration}min (+5)");
        } elseif ($actualDuration >= 15 && $actualDuration <= 120) {
            $score += 2;
            Log::info("🤖 [ML] Within reasonable lunch range: {$actualDuration}min (+2)");
        }

        // Typical lunch hours bonus
        $startHour = Carbon::parse($startPunch->punch_time)->hour;
        if ($startHour >= 11 && $startHour <= 14) {
            $score += 5;
            Log::info("🤖 [ML] Within typical lunch hours ({$startHour}:xx) (+5)");
        } elseif ($startHour >= 10 && $startHour <= 15) {
            $score += 2;
            Log::info("🤖 [ML] Within extended lunch hours ({$startHour}:xx) (+2)");
        }

        Log::info("🤖 [ML] Total score for pair: {$score}");
        return $score;
    }

    /**
     * Assign break punch types using ML predictions + chronological logic
     */
    private function assignBreakPunchTypesML($punches, &$assignedTypes, $originalPunches, $employeeId): void
    {
        Log::info("🤖 [ML] Assigning {$punches->count()} punches as breaks with ML guidance");

        $punchCount = $punches->count();

        for ($i = 0; $i < $punchCount; $i += 2) {
            if ($i >= $punchCount) break;

            $startPunch = $punches->get($i);
            if (!$startPunch) {
                continue;
            }

            $startIndex = $originalPunches->search(function($punch) use ($startPunch) {
                return $punch && $punch->id === $startPunch->id;
            });

            if ($startIndex !== false) {
                $assignedTypes[$startIndex] = $this->getPunchTypeId('Break Start');
                Log::info("🤖 [ML] Assigned Break Start to Punch ID: {$startPunch->id}");
            }

            if ($i + 1 < $punchCount) {
                $endPunch = $punches->get($i + 1);
                if (!$endPunch) {
                    continue;
                }

                $endIndex = $originalPunches->search(function($punch) use ($endPunch) {
                    return $punch && $punch->id === $endPunch->id;
                });

                if ($endIndex !== false) {
                    $assignedTypes[$endIndex] = $this->getPunchTypeId('Break End');
                    Log::info("🤖 [ML] Assigned Break End to Punch ID: {$endPunch->id}");
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
        Log::info("🤖 [ML] Using fallback individual predictions for Employee {$employeeId}");

        foreach ($punches as $punch) {
            $predictedTypeId = $this->predictPunchType($employeeId, $punch->punch_time, $punch->shift_date, $punch->external_group_id);

            if ($predictedTypeId) {
                $punchEvaluations[$punch->id]['ml'] = [
                    'punch_type_id' => $predictedTypeId,
                    'punch_state' => $this->determinePunchState($predictedTypeId),
                    'source' => 'ML Engine (Fallback)'
                ];
                Log::info("🤖 [ML] Assigned Predicted Punch Type ID: {$predictedTypeId} to Punch ID: {$punch->id}");
            } else {
                Log::warning("🤖 [ML] No reliable prediction for Punch ID: {$punch->id}");
            }
        }
    }

    private function getPunchTypeId(string $type): ?int
    {
        $id = \DB::table('punch_types')->where('name', $type)->value('id');

        if (!$id) {
            Log::warning("🤖 [ML] Punch Type '{$type}' not found.");
        }

        return $id;
    }
}
