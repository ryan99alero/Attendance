<?php

namespace App\Services\ML;

use App\Models\Punch;
use Phpml\Classification\KNearestNeighbors;
use Phpml\ModelManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
class MlPunchTypePredictorService
{
// Not used anymore    private string $modelPath = 'storage/ml/punch_model.serialized';
    private KNearestNeighbors $classifier;

    public function __construct()
    {
        Log::info("🛠 Initializing MlPunchTypePredictorService...");
        $this->classifier = new KNearestNeighbors(3); // Default to fresh model

        $modelFile = storage_path('ml/punch_model.serialized');
        $modelManager = new ModelManager();

        if (file_exists($modelFile)) {
            Log::info("✅ ML Model file found. Attempting to restore...");

            $model = $modelManager->restoreFromFile($modelFile);

            if ($model instanceof KNearestNeighbors) {
                $this->classifier = $model;
                Log::info("✅ ML Model successfully restored from file.");
            } else {
                Log::warning("⚠️ Restored model is not a KNearestNeighbors instance. Retraining...");
                $this->trainModel();
            }
        } else {
            Log::warning("⚠️ No saved model found. Training a new one...");
            $this->trainModel();
        }
    }
    public function trainModel(): void
    {
        Log::info("🔍 [ML] trainModel() was called. Training...");

        // Ensure the ML model storage directory exists
        $modelDir = storage_path('ml');
        if (!File::exists($modelDir)) {
            File::makeDirectory($modelDir, 0755, true);
            Log::info("📂 Created missing ML model directory: {$modelDir}");
        }

        // Fetch processed punch records
        $punchData = Punch::whereNotNull('punch_type_id')
            ->where('is_processed', false)
            ->orderBy('punch_time', 'asc')
            ->get();

        Log::info("📊 [ML] Fetched " . $punchData->count() . " punch records for training.");

        if ($punchData->isEmpty()) {
            Log::warning("⚠️ [ML] Insufficient data to train model.");
            return;
        }

        // Prepare data
        $samples = [];
        $labels = [];
        foreach ($punchData as $record) {
            $timeValue = strtotime($record->punch_time) % 86400;
            $samples[] = [$record->employee_id, $timeValue];
            $labels[] = $record->punch_type_id;
        }

        if (empty($samples) || empty($labels)) {
            Log::warning("⚠️ [ML] No valid samples found for training.");
            return;
        }

        $this->classifier->train($samples, $labels);

        // Ensure the model file path is correctly resolved
        $modelPath = storage_path('ml/punch_model.serialized');

        // 🔥 Save the trained model to disk
        $modelManager = new ModelManager();
        $modelManager->saveToFile($this->classifier, $modelPath);

        Log::info("✅ [ML] Model trained and saved to {$modelPath}.");
    }

    public function predictPunchType(int $employeeId, string $punchTime, int $classificationId = null): ?int
    {
        $timeValue = strtotime($punchTime) % 86400;

        try {
            // 🔥 Ensure model is trained before making a prediction
            if (empty($this->classifier)) {
                Log::warning("⚠️ [ML] Classifier is uninitialized. Training now...");
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
