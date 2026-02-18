<?php

namespace App\Providers;

use App\Helpers\KoolReportLaravelCompatibility;
use App\Models\Attendance;
use App\Models\SystemTask;
use App\Observers\AttendanceObserver;
use App\Services\Shift\ShiftScheduleService;
use Carbon\Carbon;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Initialize KoolReport Laravel 11 compatibility fixes early
        KoolReportLaravelCompatibility::initialize();

        // Correct namespace for ShiftScheduleService
        $this->app->singleton(ShiftScheduleService::class, function ($app) {
            return new ShiftScheduleService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register AttendanceObserver for automatic payroll summary recalculation
        Attendance::observe(AttendanceObserver::class);

        // Track Filament Imports in SystemTask using model events directly
        Import::created(function (Import $import) {
            Log::info('[AppServiceProvider] Import created event fired', [
                'import_id' => $import->id,
                'importer' => $import->importer,
            ]);

            $importerClass = class_basename($import->importer);
            $importerName = str_replace('Importer', '', $importerClass);

            SystemTask::create([
                'type' => SystemTask::TYPE_IMPORT,
                'name' => "{$importerName} Import",
                'description' => "Importing {$import->file_name}",
                'status' => SystemTask::STATUS_PROCESSING,
                'progress' => 0,
                'progress_message' => 'Starting import...',
                'total_records' => $import->total_rows,
                'processed_records' => 0,
                'successful_records' => 0,
                'failed_records' => 0,
                'related_model' => Import::class,
                'related_id' => $import->id,
                'file_path' => $import->file_path,
                'created_by' => $import->user_id,
                'started_at' => now(),
            ]);
        });

        Import::updated(function (Import $import) {
            $systemTask = SystemTask::where('related_model', Import::class)
                ->where('related_id', $import->id)
                ->first();

            if (! $systemTask) {
                return;
            }

            $progress = $import->total_rows > 0
                ? (int) (($import->processed_rows / $import->total_rows) * 100)
                : 0;

            if ($import->completed_at) {
                $failedRows = $import->getFailedRowsCount();
                $message = "Imported {$import->successful_rows} rows";
                if ($failedRows > 0) {
                    $message .= " ({$failedRows} failed)";
                }

                $systemTask->update([
                    'status' => ($failedRows > 0 && $import->successful_rows === 0)
                        ? SystemTask::STATUS_FAILED
                        : SystemTask::STATUS_COMPLETED,
                    'progress' => 100,
                    'progress_message' => $message,
                    'processed_records' => $import->processed_rows,
                    'successful_records' => $import->successful_rows,
                    'failed_records' => $failedRows,
                    'completed_at' => now(),
                ]);
            } else {
                $systemTask->update([
                    'progress' => min(99, $progress),
                    'progress_message' => "Processing: {$import->processed_rows}/{$import->total_rows} rows",
                    'processed_records' => $import->processed_rows,
                    'successful_records' => $import->successful_rows,
                ]);
            }
        });

        // Track Filament Exports in SystemTask
        Export::created(function (Export $export) {
            $exporterClass = class_basename($export->exporter);
            $exporterName = str_replace('Exporter', '', $exporterClass);

            SystemTask::create([
                'type' => SystemTask::TYPE_EXPORT,
                'name' => "{$exporterName} Export",
                'description' => "Exporting {$exporterName} data",
                'status' => SystemTask::STATUS_PROCESSING,
                'progress' => 0,
                'progress_message' => 'Starting export...',
                'total_records' => $export->total_rows,
                'processed_records' => 0,
                'successful_records' => 0,
                'failed_records' => 0,
                'related_model' => Export::class,
                'related_id' => $export->id,
                'created_by' => $export->user_id,
                'started_at' => now(),
            ]);
        });

        Export::updated(function (Export $export) {
            $systemTask = SystemTask::where('related_model', Export::class)
                ->where('related_id', $export->id)
                ->first();

            if (! $systemTask) {
                return;
            }

            $progress = $export->total_rows > 0
                ? (int) (($export->processed_rows / $export->total_rows) * 100)
                : 0;

            if ($export->completed_at) {
                $systemTask->update([
                    'status' => SystemTask::STATUS_COMPLETED,
                    'progress' => 100,
                    'progress_message' => "Exported {$export->successful_rows} rows",
                    'processed_records' => $export->processed_rows,
                    'successful_records' => $export->successful_rows,
                    'output_file_path' => $export->file_disk.'/'.$export->file_name,
                    'completed_at' => now(),
                ]);
            } else {
                $systemTask->update([
                    'progress' => min(99, $progress),
                    'progress_message' => "Processing: {$export->processed_rows}/{$export->total_rows} rows",
                    'processed_records' => $export->processed_rows,
                    'successful_records' => $export->successful_rows,
                ]);
            }
        });

        // Define a custom Carbon macro for formatting
        Carbon::macro('toCustomFormat', function () {
            return $this->format('Y-m-d H:i:s');
        });
    }
}
