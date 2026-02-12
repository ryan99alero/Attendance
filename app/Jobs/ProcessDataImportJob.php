<?php

namespace App\Jobs;

use App\Imports\DataImport;
use App\Models\DataImportRecord;
use App\Models\Department;
use App\Models\SystemTask;
use App\Models\User;
use App\Traits\TracksSystemTask;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessDataImportJob implements ShouldQueue
{
    use Queueable, TracksSystemTask;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Processor types for different import logic
     */
    public const PROCESSOR_NONE = 'none';

    public const PROCESSOR_EMPLOYEE = 'employee';

    public const PROCESSOR_DEPARTMENT = 'department';

    public const PROCESSOR_SHIFT_SCHEDULE = 'shift_schedule';

    public const PROCESSOR_ATTENDANCE = 'attendance';

    public function __construct(
        protected int $importRecordId,
        protected string $processorType = self::PROCESSOR_NONE,
    ) {}

    /**
     * Create an import record and dispatch the job
     */
    public static function createAndDispatch(
        string $filePath,
        string $modelClass,
        string $processorType = self::PROCESSOR_NONE,
        ?int $userId = null,
        ?string $originalFileName = null
    ): DataImportRecord {
        // Count rows in file
        $totalRows = self::countFileRows($filePath);

        // Create import record
        $import = DataImportRecord::create([
            'model_type' => $modelClass,
            'original_file_name' => $originalFileName ?? basename($filePath),
            'file_path' => $filePath,
            'status' => DataImportRecord::STATUS_PENDING,
            'progress' => 0,
            'progress_message' => 'Queued for processing...',
            'total_rows' => $totalRows,
            'imported_by' => $userId,
        ]);

        // Dispatch job
        self::dispatch($import->id, $processorType);

        return $import;
    }

    /**
     * Count rows in a spreadsheet file
     */
    protected static function countFileRows(string $filePath): int
    {
        try {
            $fullPath = Storage::disk('local')->path($filePath);
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Subtract 1 for header row
            return max(0, $worksheet->getHighestRow() - 1);
        } catch (\Exception $e) {
            Log::warning("[DataImportJob] Could not count rows: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $import = DataImportRecord::find($this->importRecordId);

        if (! $import) {
            Log::error("[DataImportJob] Import record not found: {$this->importRecordId}");

            return;
        }

        // Create system task for tracking
        $this->initializeSystemTask(
            type: SystemTask::TYPE_IMPORT,
            name: "{$import->getModelDisplayName()} Import",
            description: "Importing {$import->original_file_name}",
            totalRecords: $import->total_rows,
            relatedModel: $import->model_type,
            userId: $import->imported_by
        );

        Log::info("[DataImportJob] Starting import for {$import->model_type} from {$import->file_path}");

        $import->markProcessing();
        $import->updateProgress(5, 'Reading file...');
        $this->updateTaskProgress(5, 'Reading file...');

        $fullPath = Storage::disk('local')->path($import->file_path);

        if (! file_exists($fullPath)) {
            Log::error("[DataImportJob] File not found: {$fullPath}");
            $import->markFailed("File not found: {$import->original_file_name}");
            $this->notifyUser($import, false);

            return;
        }

        try {
            $import->updateProgress(10, 'Processing records...');

            // Create the import with the appropriate processor and progress callback
            $importService = new DataImport(
                $import->model_type,
                $this->getRowProcessor($import->model_type),
                function (int $processed, bool $success) use ($import) {
                    $import->incrementProcessed($success);

                    // Update progress message periodically
                    if ($processed % 25 === 0 || $processed === $import->total_rows) {
                        $progress = $import->total_rows > 0
                            ? 10 + (int) (($processed / $import->total_rows) * 80)
                            : 50;
                        $import->updateProgress($progress, "Processing row {$processed}...");
                        $this->updateTaskProgress($progress, "Processing row {$processed}...", $processed);
                    }
                }
            );

            Excel::import($importService, $fullPath);

            $failedRecords = $importService->getFailedRecords();

            $import->updateProgress(95, 'Finalizing...');

            if (! empty($failedRecords)) {
                // Export failed records to a file
                $errorFileName = $this->exportFailedRecords($failedRecords, $import->model_type);
                $import->update([
                    'error_file_path' => "import_errors/{$errorFileName}",
                    'failed_rows' => count($failedRecords),
                ]);
            }

            $import->markCompleted();

            $this->updateTaskRecords($import->processed_rows, $import->successful_rows, $import->failed_rows);
            $this->completeTask("Imported {$import->successful_rows} records");
            $this->notifyUser($import, true);

            // Clean up the uploaded file
            Storage::disk('local')->delete($import->file_path);
            Log::info('[DataImportJob] Import completed successfully');

        } catch (\Exception $e) {
            Log::error("[DataImportJob] Import failed: {$e->getMessage()}");
            $import->markFailed($e->getMessage());
            $this->failTask($e->getMessage());
            $this->notifyUser($import, false);
        }
    }

    /**
     * Get the row processor based on processor type
     */
    protected function getRowProcessor(string $modelClass): ?callable
    {
        return match ($this->processorType) {
            self::PROCESSOR_EMPLOYEE => function (array $row) {
                return $this->processEmployeeRow($row);
            },
            self::PROCESSOR_DEPARTMENT => function (array $row) {
                return $this->processDepartmentRow($row);
            },
            self::PROCESSOR_SHIFT_SCHEDULE => function (array $row) {
                return $this->processShiftScheduleRow($row);
            },
            self::PROCESSOR_ATTENDANCE => function (array $row) {
                return $this->processAttendanceRow($row);
            },
            default => null,
        };
    }

    /**
     * Process employee row - map external_department_id to department_id
     */
    protected function processEmployeeRow(array $row): array
    {
        if (isset($row['external_department_id']) && ! empty($row['external_department_id'])) {
            $externalDepartmentId = str_pad((string) $row['external_department_id'], 3, '0', STR_PAD_LEFT);
            $mappedDepartment = Department::where('external_department_id', $externalDepartmentId)->first();

            if ($mappedDepartment) {
                $row['department_id'] = $mappedDepartment->id;
                Log::info("Mapped external_department_id {$externalDepartmentId} to department_id {$row['department_id']}");
            } else {
                Log::warning("No department found for external_department_id: {$externalDepartmentId}");
                $row['department_id'] = null;
            }
        }

        return $row;
    }

    /**
     * Process department row
     */
    protected function processDepartmentRow(array $row): array
    {
        // Pad external_department_id if provided
        if (isset($row['external_department_id']) && ! empty($row['external_department_id'])) {
            $row['external_department_id'] = str_pad((string) $row['external_department_id'], 3, '0', STR_PAD_LEFT);
        }

        return $row;
    }

    /**
     * Process shift schedule row
     */
    protected function processShiftScheduleRow(array $row): array
    {
        // Add any shift schedule specific processing here
        return $row;
    }

    /**
     * Process attendance row
     */
    protected function processAttendanceRow(array $row): array
    {
        // Map employee external_id to employee_id if provided
        if (isset($row['employee_external_id']) && ! empty($row['employee_external_id'])) {
            $employee = \App\Models\Employee::where('external_id', $row['employee_external_id'])->first();
            if ($employee) {
                $row['employee_id'] = $employee->id;
            }
        }

        return $row;
    }

    /**
     * Export failed records to a file
     */
    protected function exportFailedRecords(array $failedRecords, string $modelClass): string
    {
        $tableName = (new $modelClass)->getTable();
        $fileName = "{$tableName}_import_errors_".now()->format('Y-m-d_His').'.csv';
        $path = "import_errors/{$fileName}";

        $content = '';

        // Add headers
        if (! empty($failedRecords)) {
            $content .= implode(',', array_keys($failedRecords[0]))."\n";

            // Add data rows
            foreach ($failedRecords as $record) {
                $content .= implode(',', array_map(function ($value) {
                    // Escape quotes and wrap in quotes if contains comma
                    if (str_contains((string) $value, ',') || str_contains((string) $value, '"')) {
                        return '"'.str_replace('"', '""', $value).'"';
                    }

                    return $value;
                }, $record))."\n";
            }
        }

        Storage::disk('local')->put($path, $content);

        return $fileName;
    }

    /**
     * Send notification to user
     */
    protected function notifyUser(DataImportRecord $import, bool $success): void
    {
        if (! $import->imported_by) {
            return;
        }

        $user = User::find($import->imported_by);
        if (! $user) {
            return;
        }

        $modelName = $import->getModelDisplayName();

        if ($success) {
            $body = "Imported {$import->successful_rows} {$modelName} records";
            if ($import->failed_rows > 0) {
                $body .= " ({$import->failed_rows} failed)";
            }

            Notification::make()
                ->success()
                ->title("{$modelName} Import Complete")
                ->body($body)
                ->sendToDatabase($user);
        } else {
            Notification::make()
                ->danger()
                ->title("{$modelName} Import Failed")
                ->body($import->error_message ?? 'Unknown error')
                ->sendToDatabase($user);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[DataImportJob] Job failed: {$exception->getMessage()}");

        $import = DataImportRecord::find($this->importRecordId);
        if ($import) {
            $import->markFailed($exception->getMessage());
            $this->failTask($exception->getMessage());
            $this->notifyUser($import, false);
        }
    }
}
