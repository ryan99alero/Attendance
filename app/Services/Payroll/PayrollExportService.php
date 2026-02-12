<?php

namespace App\Services\Payroll;

use App\Models\IntegrationConnection;
use App\Models\PayPeriod;
use App\Models\PayrollExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollExportService
{
    protected PayrollAggregationService $aggregationService;

    protected AdpExportService $adpExportService;

    public function __construct(
        PayrollAggregationService $aggregationService,
        AdpExportService $adpExportService
    ) {
        $this->aggregationService = $aggregationService;
        $this->adpExportService = $adpExportService;
    }

    /**
     * Export payroll data for a pay period and provider
     *
     * @param  string  $format  csv|xlsx|json|xml
     */
    public function export(
        PayPeriod $payPeriod,
        IntegrationConnection $provider,
        string $format = 'csv'
    ): PayrollExport {
        Log::info("[PayrollExport] Starting export for PayPeriod {$payPeriod->id}, Provider {$provider->name}, Format {$format}");

        // Route to specialized export service if applicable
        if ($this->isAdpFlatFile($provider)) {
            return $this->adpExportService->export($payPeriod, $provider);
        }

        // Create export record
        $export = PayrollExport::create([
            'pay_period_id' => $payPeriod->id,
            'integration_connection_id' => $provider->id,
            'format' => $format,
            'file_name' => PayrollExport::generateFileName($provider, $payPeriod, $format),
            'status' => PayrollExport::STATUS_PROCESSING,
            'exported_by' => auth()->id(),
        ]);

        try {
            // Get aggregated data for this provider
            $data = $this->aggregationService->getDataForProvider($payPeriod, $provider);

            if ($data->isEmpty()) {
                $export->markFailed('No employees assigned to this payroll provider');

                return $export;
            }

            // Generate file based on format
            $filePath = match ($format) {
                'csv' => $this->generateCsv($data, $export->file_name),
                'xlsx' => $this->generateXlsx($data, $export->file_name),
                'json' => $this->generateJson($data, $export->file_name),
                'xml' => $this->generateXml($data, $export->file_name),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
            };

            // Handle destination
            if ($provider->export_destination === 'path' && $provider->export_path) {
                $this->moveToPath($filePath, $provider->export_path, $export->file_name);
                $export->update(['file_path' => $provider->export_path.'/'.$export->file_name]);
            } else {
                $export->update(['file_path' => $filePath]);
            }

            $export->markCompleted(
                $data->count(),
                $data->count() // Each row is one employee with all their hour types
            );

            Log::info("[PayrollExport] Export completed: {$export->file_name}");

        } catch (\Exception $e) {
            Log::error("[PayrollExport] Export failed: {$e->getMessage()}");
            $export->markFailed($e->getMessage());
        }

        return $export;
    }

    /**
     * Check if this is an ADP flat file export
     */
    protected function isAdpFlatFile(IntegrationConnection $provider): bool
    {
        return $provider->driver === IntegrationConnection::DRIVER_ADP
            && $provider->isFlatFileMethod();
    }

    /**
     * Export all payroll providers for a pay period
     *
     * @return Collection<PayrollExport>
     */
    public function exportAll(PayPeriod $payPeriod): Collection
    {
        $exports = collect();

        $providers = IntegrationConnection::payrollProviders()
            ->active()
            ->get();

        foreach ($providers as $provider) {
            foreach ($provider->getEnabledFormats() as $format) {
                $export = $this->export($payPeriod, $provider, $format);
                $exports->push($export);
            }
        }

        return $exports;
    }

    /**
     * Generate CSV export
     *
     * @return string File path
     */
    protected function generateCsv(Collection $data, string $fileName): string
    {
        $path = 'payroll_exports/'.$fileName;

        $headers = $this->getExportHeaders($data);

        $content = implode(',', $headers)."\n";

        foreach ($data as $row) {
            $line = [];
            foreach ($headers as $header) {
                $key = strtolower(str_replace(' ', '_', $header));
                $value = $row[$key] ?? '';
                // Escape quotes and wrap in quotes if contains comma
                if (str_contains((string) $value, ',') || str_contains((string) $value, '"')) {
                    $value = '"'.str_replace('"', '""', $value).'"';
                }
                $line[] = $value;
            }
            $content .= implode(',', $line)."\n";
        }

        Storage::disk('local')->put($path, $content);

        return Storage::disk('local')->path($path);
    }

    /**
     * Generate XLSX export
     *
     * @return string File path
     */
    protected function generateXlsx(Collection $data, string $fileName): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = $this->getExportHeaders($data);

        // Write headers
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $col++;
        }

        // Write data
        $rowNum = 2;
        foreach ($data as $row) {
            $col = 'A';
            foreach ($headers as $header) {
                $key = strtolower(str_replace(' ', '_', $header));
                $sheet->setCellValue($col.$rowNum, $row[$key] ?? '');
                $col++;
            }
            $rowNum++;
        }

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $path = 'payroll_exports/'.$fileName;
        $fullPath = Storage::disk('local')->path($path);

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($fullPath);

        return $fullPath;
    }

    /**
     * Generate JSON export
     *
     * @return string File path
     */
    protected function generateJson(Collection $data, string $fileName): string
    {
        $path = 'payroll_exports/'.$fileName;

        $export = [
            'generated_at' => now()->toIso8601String(),
            'record_count' => $data->count(),
            'employees' => $data->toArray(),
        ];

        Storage::disk('local')->put($path, json_encode($export, JSON_PRETTY_PRINT));

        return Storage::disk('local')->path($path);
    }

    /**
     * Generate XML export
     *
     * @return string File path
     */
    protected function generateXml(Collection $data, string $fileName): string
    {
        $path = 'payroll_exports/'.$fileName;

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><payroll_export></payroll_export>');
        $xml->addAttribute('generated_at', now()->toIso8601String());
        $xml->addAttribute('record_count', (string) $data->count());

        $employees = $xml->addChild('employees');

        foreach ($data as $row) {
            $employee = $employees->addChild('employee');
            foreach ($row as $key => $value) {
                // Sanitize key for XML element name
                $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
                $employee->addChild($key, htmlspecialchars((string) $value));
            }
        }

        Storage::disk('local')->put($path, $xml->asXML());

        return Storage::disk('local')->path($path);
    }

    /**
     * Move file to external path
     */
    protected function moveToPath(string $sourcePath, string $destDir, string $fileName): void
    {
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $destPath = rtrim($destDir, '/').'/'.$fileName;
        copy($sourcePath, $destPath);

        Log::info("[PayrollExport] File copied to: {$destPath}");
    }

    /**
     * Get export headers based on data
     */
    protected function getExportHeaders(Collection $data): array
    {
        if ($data->isEmpty()) {
            return [];
        }

        $firstRow = $data->first();

        // Convert keys to readable headers
        return collect(array_keys($firstRow))
            ->map(fn ($key) => ucwords(str_replace('_', ' ', $key)))
            ->toArray();
    }

    /**
     * Download an export file
     */
    public function download(PayrollExport $export): StreamedResponse
    {
        $mimeTypes = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'json' => 'application/json',
            'xml' => 'application/xml',
        ];

        return response()->streamDownload(function () use ($export) {
            echo file_get_contents($export->file_path);
        }, $export->file_name, [
            'Content-Type' => $mimeTypes[$export->format] ?? 'application/octet-stream',
        ]);
    }

    /**
     * Re-export a failed or pending export
     */
    public function retry(PayrollExport $export): PayrollExport
    {
        return $this->export(
            $export->payPeriod,
            $export->integrationConnection,
            $export->format
        );
    }
}
