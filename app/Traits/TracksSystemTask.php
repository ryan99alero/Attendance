<?php

namespace App\Traits;

use App\Models\SystemTask;

/**
 * Trait for queue jobs to track their progress in the system_tasks table
 */
trait TracksSystemTask
{
    protected ?SystemTask $systemTask = null;

    /**
     * Initialize a system task for tracking
     */
    protected function initializeSystemTask(
        string $type,
        string $name,
        ?string $description = null,
        ?int $totalRecords = null,
        ?string $relatedModel = null,
        ?int $relatedId = null,
        ?int $userId = null
    ): SystemTask {
        $this->systemTask = SystemTask::create([
            'type' => $type,
            'name' => $name,
            'description' => $description,
            'status' => SystemTask::STATUS_PROCESSING,
            'progress' => 0,
            'progress_message' => 'Starting...',
            'total_records' => $totalRecords,
            'related_model' => $relatedModel,
            'related_id' => $relatedId,
            'created_by' => $userId ?? auth()->id(),
            'started_at' => now(),
        ]);

        return $this->systemTask;
    }

    /**
     * Update system task progress
     */
    protected function updateTaskProgress(int $progress, ?string $message = null, ?int $processed = null): void
    {
        if (! $this->systemTask) {
            return;
        }

        $data = [
            'progress' => min(100, max(0, $progress)),
        ];

        if ($message !== null) {
            $data['progress_message'] = $message;
        }

        if ($processed !== null) {
            $data['processed_records'] = $processed;
        }

        $this->systemTask->update($data);
    }

    /**
     * Mark system task as completed
     */
    protected function completeTask(?string $message = null, ?string $outputFilePath = null): void
    {
        if (! $this->systemTask) {
            return;
        }

        $data = [
            'status' => SystemTask::STATUS_COMPLETED,
            'progress' => 100,
            'progress_message' => $message ?? 'Completed successfully',
            'completed_at' => now(),
        ];

        if ($outputFilePath) {
            $data['output_file_path'] = $outputFilePath;
        }

        $this->systemTask->update($data);
    }

    /**
     * Mark system task as failed
     */
    protected function failTask(string $errorMessage): void
    {
        if (! $this->systemTask) {
            \Illuminate\Support\Facades\Log::warning("[TracksSystemTask] failTask called but systemTask is null");

            return;
        }

        \Illuminate\Support\Facades\Log::info("[TracksSystemTask] Marking task {$this->systemTask->id} as failed: {$errorMessage}");

        $this->systemTask->update([
            'status' => SystemTask::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Update record counts on the task
     */
    protected function updateTaskRecords(int $processed, int $successful, int $failed): void
    {
        if (! $this->systemTask) {
            return;
        }

        $this->systemTask->update([
            'processed_records' => $processed,
            'successful_records' => $successful,
            'failed_records' => $failed,
        ]);
    }

    /**
     * Get the current system task
     */
    protected function getSystemTask(): ?SystemTask
    {
        return $this->systemTask;
    }

    /**
     * Load an existing system task by ID
     */
    protected function loadSystemTask(int $taskId): ?SystemTask
    {
        $this->systemTask = SystemTask::find($taskId);

        return $this->systemTask;
    }
}
