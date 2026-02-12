<?php

namespace App\Jobs;

use App\Models\IntegrationConnection;
use App\Models\PayPeriod;
use App\Models\PayrollExport;
use App\Models\SystemTask;
use App\Models\User;
use App\Services\Payroll\AdpExportService;
use App\Services\Payroll\PayrollAggregationService;
use App\Traits\TracksSystemTask;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProcessPayrollExportJob implements ShouldQueue
{
    use Queueable, TracksSystemTask;

    public int $timeout = 600; // 10 minutes

    public int $tries = 1;

    public function __construct(
        protected int $exportId,
        protected ?int $userId = null
    ) {}

    public function handle(
        PayrollAggregationService $aggregationService,
        AdpExportService $adpExportService
    ): void {
        $export = PayrollExport::find($this->exportId);

        if (! $export) {
            Log::error("[PayrollExportJob] Export not found: {$this->exportId}");

            return;
        }

        $payPeriod = $export->payPeriod;
        $provider = $export->integrationConnection;

        if (! $payPeriod || ! $provider) {
            $export->markFailed('Pay period or provider not found');
            // Note: SystemTask not created yet at this point
            $this->notifyUser($export, false);

            return;
        }

        // Create system task for tracking
        $this->initializeSystemTask(
            type: SystemTask::TYPE_EXPORT,
            name: "Payroll Export: {$provider->name}",
            description: "{$payPeriod->name} - {$export->format} format",
            relatedModel: PayrollExport::class,
            relatedId: $export->id,
            userId: $this->userId
        );

        Log::info("[PayrollExportJob] Starting export {$export->id} for PayPeriod {$payPeriod->id}");

        try {
            // Step 1: Initialize
            $this->updateProgress($export, 5, 'Initializing export...');
            $this->updateTaskProgress(5, 'Initializing export...');

            // Check for ADP flat file
            if ($this->isAdpFlatFile($provider)) {
                $this->updateProgress($export, 10, 'Generating ADP flat file...');
                $this->updateTaskProgress(10, 'Generating ADP flat file...');
                $this->processAdpExport($export, $payPeriod, $provider, $adpExportService);

                return;
            }

            // Step 2: Aggregate data
            $this->updateProgress($export, 10, 'Aggregating payroll data...');
            $aggregationService->aggregatePayPeriod($payPeriod);

            // Step 3: Get data for provider
            $this->updateProgress($export, 30, 'Fetching employee data...');
            $data = $aggregationService->getDataForProvider($payPeriod, $provider);

            if ($data->isEmpty()) {
                $export->markFailed('No employees assigned to this payroll provider');
                $this->failTask('No employees assigned to this payroll provider');
                $this->notifyUser($export, false);

                return;
            }

            // Update total count
            $export->update(['total_employees' => $data->count()]);

            // Step 4: Generate file
            $this->updateProgress($export, 50, "Generating {$export->format} file...");

            $filePath = match ($export->format) {
                'csv' => $this->generateCsv($data, $export),
                'xlsx' => $this->generateXlsx($data, $export),
                'json' => $this->generateJson($data, $export),
                'xml' => $this->generateXml($data, $export),
                default => throw new \InvalidArgumentException("Unsupported format: {$export->format}"),
            };

            // Step 5: Handle destination
            $this->updateProgress($export, 80, 'Saving file...');

            if ($provider->export_destination === 'path' && $provider->export_path) {
                $this->moveToPath($filePath, $provider->export_path, $export->file_name);
                $export->update(['file_path' => $provider->export_path.'/'.$export->file_name]);
            } else {
                $export->update(['file_path' => $filePath]);
            }

            // Step 6: Complete
            $this->updateProgress($export, 100, 'Export complete!');
            $export->markCompleted($data->count(), $data->count());

            $this->updateTaskRecords($data->count(), $data->count(), 0);
            $this->completeTask("Exported {$data->count()} employees", $export->file_path);

            Log::info("[PayrollExportJob] Export completed: {$export->file_name}");
            $this->notifyUser($export, true);

        } catch (\Exception $e) {
            Log::error("[PayrollExportJob] Export failed: {$e->getMessage()}");
            $export->markFailed($e->getMessage());
            $this->failTask($e->getMessage());
            $this->notifyUser($export, false);
        }
    }

    protected function isAdpFlatFile(IntegrationConnection $provider): bool
    {
        return $provider->driver === IntegrationConnection::DRIVER_ADP
            && $provider->isFlatFileMethod();
    }

    protected function processAdpExport(
        PayrollExport $export,
        PayPeriod $payPeriod,
        IntegrationConnection $provider,
        AdpExportService $adpExportService
    ): void {
        try {
            // Let ADP service handle its own progress updates
            $adpExport = $adpExportService->exportWithProgress($payPeriod, $provider, $export);

            Log::info("[PayrollExportJob] ADP export returned - completed: " . ($adpExport->isCompleted() ? 'yes' : 'no') . ", error: " . ($adpExport->error_message ?? 'none'));

            if ($adpExport->isCompleted()) {
                $this->updateTaskRecords($adpExport->employee_count ?? 0, $adpExport->employee_count ?? 0, 0);
                $this->completeTask("Exported {$adpExport->employee_count} employees", $adpExport->file_path);
            } else {
                Log::info("[PayrollExportJob] Calling failTask with: " . ($adpExport->error_message ?? 'Export failed'));
                $this->failTask($adpExport->error_message ?? 'Export failed');
                Log::info("[PayrollExportJob] failTask completed, systemTask: " . ($this->getSystemTask()?->id ?? 'null'));
            }

            $this->notifyUser($adpExport, $adpExport->isCompleted());
        } catch (\Exception $e) {
            Log::error("[PayrollExportJob] ADP export failed: {$e->getMessage()}");
            $export->markFailed($e->getMessage());
            $this->failTask($e->getMessage());
            $this->notifyUser($export, false);
        }
    }

    protected function updateProgress(PayrollExport $export, int $progress, string $message): void
    {
        $export->update([
            'progress' => $progress,
            'progress_message' => $message,
        ]);
    }

    protected function notifyUser(PayrollExport $export, bool $success): void
    {
        if (! $this->userId) {
            return;
        }

        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        if ($success) {
            Notification::make()
                ->success()
                ->title('Export Completed')
                ->body("Exported {$export->employee_count} employees to {$export->file_name}")
                ->actions([
                    \Filament\Actions\Action::make('download')
                        ->label('Download')
                        ->url(route('payroll.export.download', $export->id))
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($user);
        } else {
            Notification::make()
                ->danger()
                ->title('Export Failed')
                ->body($export->error_message ?? 'Unknown error occurred')
                ->sendToDatabase($user);
        }
    }

    protected function generateCsv($data, PayrollExport $export): string
    {
        $path = 'payroll_exports/'.$export->file_name;
        $headers = $this->getExportHeaders($data);

        $content = implode(',', $headers)."\n";
        $processed = 0;
        $total = $data->count();

        foreach ($data as $row) {
            $line = [];
            foreach ($headers as $header) {
                $key = strtolower(str_replace(' ', '_', $header));
                $value = $row[$key] ?? '';
                if (str_contains((string) $value, ',') || str_contains((string) $value, '"')) {
                    $value = '"'.str_replace('"', '""', $value).'"';
                }
                $line[] = $value;
            }
            $content .= implode(',', $line)."\n";

            $processed++;
            if ($processed % 50 === 0 || $processed === $total) {
                $progress = 50 + (int) (($processed / $total) * 30);
                $export->update([
                    'progress' => $progress,
                    'progress_message' => "Processing employee {$processed} of {$total}...",
                    'processed_employees' => $processed,
                ]);
            }
        }

        Storage::disk('local')->put($path, $content);

        return Storage::disk('local')->path($path);
    }

    protected function generateXlsx($data, PayrollExport $export): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $this->getExportHeaders($data);

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $col++;
        }

        $rowNum = 2;
        $processed = 0;
        $total = $data->count();

        foreach ($data as $row) {
            $col = 'A';
            foreach ($headers as $header) {
                $key = strtolower(str_replace(' ', '_', $header));
                $sheet->setCellValue($col.$rowNum, $row[$key] ?? '');
                $col++;
            }
            $rowNum++;

            $processed++;
            if ($processed % 50 === 0 || $processed === $total) {
                $progress = 50 + (int) (($processed / $total) * 30);
                $export->update([
                    'progress' => $progress,
                    'progress_message' => "Processing employee {$processed} of {$total}...",
                    'processed_employees' => $processed,
                ]);
            }
        }

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $path = 'payroll_exports/'.$export->file_name;
        $fullPath = Storage::disk('local')->path($path);

        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($fullPath);

        return $fullPath;
    }

    protected function generateJson($data, PayrollExport $export): string
    {
        $path = 'payroll_exports/'.$export->file_name;

        $this->updateProgress($export, 70, 'Building JSON structure...');

        $jsonExport = [
            'generated_at' => now()->toIso8601String(),
            'record_count' => $data->count(),
            'employees' => $data->toArray(),
        ];

        Storage::disk('local')->put($path, json_encode($jsonExport, JSON_PRETTY_PRINT));

        return Storage::disk('local')->path($path);
    }

    protected function generateXml($data, PayrollExport $export): string
    {
        $path = 'payroll_exports/'.$export->file_name;

        $this->updateProgress($export, 60, 'Building XML structure...');

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><payroll_export></payroll_export>');
        $xml->addAttribute('generated_at', now()->toIso8601String());
        $xml->addAttribute('record_count', (string) $data->count());

        $employees = $xml->addChild('employees');
        $processed = 0;
        $total = $data->count();

        foreach ($data as $row) {
            $employee = $employees->addChild('employee');
            foreach ($row as $key => $value) {
                $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
                $employee->addChild($key, htmlspecialchars((string) $value));
            }

            $processed++;
            if ($processed % 50 === 0 || $processed === $total) {
                $progress = 60 + (int) (($processed / $total) * 20);
                $export->update([
                    'progress' => $progress,
                    'progress_message' => "Processing employee {$processed} of {$total}...",
                    'processed_employees' => $processed,
                ]);
            }
        }

        Storage::disk('local')->put($path, $xml->asXML());

        return Storage::disk('local')->path($path);
    }

    protected function getExportHeaders($data): array
    {
        if ($data->isEmpty()) {
            return [];
        }

        $firstRow = $data->first();

        return collect(array_keys($firstRow))
            ->map(fn ($key) => ucwords(str_replace('_', ' ', $key)))
            ->toArray();
    }

    protected function moveToPath(string $sourcePath, string $destDir, string $fileName): void
    {
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $destPath = rtrim($destDir, '/').'/'.$fileName;
        copy($sourcePath, $destPath);

        Log::info("[PayrollExportJob] File copied to: {$destPath}");
    }
}
