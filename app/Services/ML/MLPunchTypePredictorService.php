<?php

namespace App\Services\ML;

use App\Models\Attendance;
use Phpml\Classification\KNearestNeighbors;
use Phpml\ModelManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MLPunchTypePredictorService
{
    private KNearestNeighbors $classifier;
    private string $modelPath;

    public function __construct()
    {
        Log::info("[ML] Initializing MLPunchTypePredictorService...");

        $this->modelPath = storage_path('ml/punch_model.serialized');
        $this->classifier = new KNearestNeighbors(3); // Default classifier with k=3

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
            ->where('is_processed', true)
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

        foreach ($punches as $punch) {
            $predictedTypeId = $this->predictPunchType($employeeId, $punch->punch_time, $punch->shift_date, $punch->external_group_id);

            if ($predictedTypeId) {
                $punchEvaluations[$punch->id]['ml'] = [
                    'punch_type_id' => $predictedTypeId,
                    'punch_state' => $this->determinePunchState($predictedTypeId),
                ];
                Log::info("🤖 [ML] Assigned Predicted Punch Type ID: {$predictedTypeId} to Punch ID: {$punch->id}");
            } else {
                Log::warning("⚠️ [ML] No reliable prediction for Punch ID: {$punch->id}");
            }
        }
    }

// ✅ New Function: Check if Training Data Exists
    private function isTrainingDataInsufficient(): bool
    {
        return \DB::table('punches')->where('is_processed', true)->count() < 10;
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
        } catch (\Exception $e) {
            Log::error("[ML] Prediction failed. Error: " . $e->getMessage());
            return null;
        }
    }

    private function determinePunchState(int $punchTypeId): string
    {
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
}
