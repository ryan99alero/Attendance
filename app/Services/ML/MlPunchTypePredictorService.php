<?php

namespace App\Services\ML;

use App\Models\Punch;
use Phpml\Classification\KNearestNeighbors;
use Phpml\ModelManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MlPunchTypePredictorService
{
    private string $modelPath = 'storage/ml/punch_model.serialized';
    private KNearestNeighbors $classifier;

    public function __construct()
    {
        Log::info("🛠 Initializing MlPunchTypePredictorService...");
        $this->classifier = new KNearestNeighbors(3); // ✅ Using KNN with k=3
        Log::info("✅ Initialized MlPunchTypePredictorService with KNN Model.");
    }

    public function trainModel(): void
    {
        Log::info("🔍 [ML] trainModel() was called. Forcing full dataset training...");

        // Fetch ALL processed punch records with a valid punch_type_id
        $punchData = Punch::whereNotNull('punch_type_id')
            ->where('is_processed', false) // ✅ Only processed punches
            ->orderBy('punch_time', 'asc') // Sort oldest to newest
            ->get();

        Log::info("📊 [ML] Fetched " . $punchData->count() . " punch records for training.");

        if ($punchData->isEmpty()) {
            Log::warning("⚠️ [ML] Insufficient data to train model.");
            return;
        }

        // Prepare data for ML - Ensure Consistent Features
        $samples = [];
        $labels = [];
        foreach ($punchData as $record) {
            $timeValue = strtotime($record->punch_time) % 86400; // Normalize time
            $samples[] = [$record->employee_id, $timeValue]; // ✅ Match features
            $labels[] = $record->punch_type_id;
        }

        if (empty($samples) || empty($labels)) {
            Log::warning("⚠️ [ML] No valid samples found for training.");
            return;
        }

        $this->classifier->train($samples, $labels);
        Log::info("✅ [ML] Model trained with " . count($samples) . " punch records.");
    }

    public function predictPunchType(int $employeeId, string $punchTime, int $classificationId = null): ?int
    {
        $timeValue = strtotime($punchTime) % 86400;

        try {
            // Ensure model is trained before making a prediction
            if (empty($this->classifier)) {
                Log::warning("⚠️ [ML] Model is not trained. Training now...");
                $this->trainModel();
            }

            $inputData = [$employeeId, $timeValue]; // ✅ MATCHES TRAINING FEATURES

            $predicted = $this->classifier->predict($inputData);

            Log::info("🤖 [ML] Prediction: Employee ID: {$employeeId}, Punch Time: {$punchTime}, Classification: " . ($classificationId ?? 'None') . " -> Predicted Type ID: " . ($predicted ?? 'NULL'));

            return is_numeric($predicted) ? (int) $predicted : null;
        } catch (\Exception $e) {
            Log::error("❌ [ML] Prediction failed for Employee ID: {$employeeId}, Punch Time: {$punchTime}. Error: " . $e->getMessage());
            return null;
        }
    }
}
