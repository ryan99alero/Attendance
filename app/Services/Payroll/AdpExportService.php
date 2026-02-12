<?php

namespace App\Services\Payroll;

use App\Models\Classification;
use App\Models\IntegrationConnection;
use App\Models\PayPeriod;
use App\Models\PayPeriodEmployeeSummary;
use App\Models\PayrollExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdpExportService
{
    /**
     * ADP CSV Column Headers (21 columns as per spec)
     */
    protected const HEADERS = [
        'Co Code',
        'Batch ID',
        'File #',
        'Temp Dept',
        'Temp Rate',
        'Reg Hours',
        'O/T Hours',
        'Hours 3 Code',
        'Hours 3 Amount',
        'Hours 3 Code',
        'Hours 3 Amount',
        'Hours 3 Code',
        'Hours 3 Amount',
        'Earnings 3 Code',
        'Earnings 3 Amount',
        'Earnings 3 Code',
        'Earnings 3 Amount',
        'Earnings 3 Code',
        'Earnings 3 Amount',
        'Memo Code',
        'Memo Amount',
    ];

    /**
     * Export payroll data in ADP format
     */
    public function export(
        PayPeriod $payPeriod,
        IntegrationConnection $provider
    ): PayrollExport {
        Log::info("[AdpExport] Starting ADP export for PayPeriod {$payPeriod->id}, Provider {$provider->name}");

        $companyCode = $provider->adp_company_code ?? 'ADP';
        $fileName = $this->generateFileName($companyCode);

        // Create export record
        $export = PayrollExport::create([
            'pay_period_id' => $payPeriod->id,
            'integration_connection_id' => $provider->id,
            'format' => 'csv',
            'file_name' => $fileName,
            'status' => PayrollExport::STATUS_PROCESSING,
            'exported_by' => auth()->id(),
        ]);

        try {
            // Get employee summaries for this provider
            $employeeData = $this->getEmployeeData($payPeriod, $provider);

            if ($employeeData->isEmpty()) {
                $export->markFailed('No employees assigned to this payroll provider');

                // Also update any related SystemTask directly
                \App\Models\SystemTask::where('related_model', PayrollExport::class)
                    ->where('related_id', $export->id)
                    ->where('status', \App\Models\SystemTask::STATUS_PROCESSING)
                    ->update([
                        'status' => \App\Models\SystemTask::STATUS_FAILED,
                        'error_message' => 'No employees assigned to this payroll provider',
                        'completed_at' => now(),
                    ]);

                return $export;
            }

            // Generate ADP-formatted CSV
            $filePath = $this->generateAdpCsv($employeeData, $fileName, $companyCode, $payPeriod);

            // Handle destination
            if ($provider->export_destination === 'path' && $provider->export_path) {
                $this->moveToPath($filePath, $provider->export_path, $fileName);
                $export->update(['file_path' => $provider->export_path.'/'.$fileName]);
            } else {
                $export->update(['file_path' => $filePath]);
            }

            $export->markCompleted(
                $employeeData->count(),
                $employeeData->count()
            );

            Log::info("[AdpExport] Export completed: {$fileName}");

        } catch (\Exception $e) {
            Log::error("[AdpExport] Export failed: {$e->getMessage()}");
            $export->markFailed($e->getMessage());
        }

        return $export;
    }

    /**
     * Generate ADP-compliant filename: PRcccEPI.csv
     */
    public function generateFileName(string $companyCode): string
    {
        // Pad 2-char codes with underscore
        if (strlen($companyCode) === 2) {
            $companyCode .= '_';
        }

        return "PR{$companyCode}EPI.csv";
    }

    /**
     * Get employee data with hour breakdowns
     */
    protected function getEmployeeData(PayPeriod $payPeriod, IntegrationConnection $provider): Collection
    {
        // Get all summaries grouped by employee
        $summaries = PayPeriodEmployeeSummary::where('pay_period_id', $payPeriod->id)
            ->whereHas('employee', function ($query) use ($provider) {
                $query->where('payroll_provider_id', $provider->id)
                    ->where('is_active', true);
            })
            ->with(['employee', 'employee.department', 'classification'])
            ->get()
            ->groupBy('employee_id');

        // Load classification mapping
        $classificationMap = $this->getClassificationMap();

        return $summaries->map(function ($employeeSummaries, $employeeId) use ($classificationMap) {
            $employee = $employeeSummaries->first()->employee;

            $data = [
                'employee_id' => $employeeId,
                'file_number' => $employee->external_id,
                'temp_dept' => null, // Optional override
                'temp_rate' => null, // Optional override
                'regular_hours' => 0,
                'overtime_hours' => 0,
                'hours_3' => [], // Array of [code, amount] pairs
                'earnings_3' => [], // Array of [code, amount] pairs
            ];

            foreach ($employeeSummaries as $summary) {
                $classification = $summary->classification;
                $hours = (float) $summary->hours;

                if ($hours <= 0) {
                    continue;
                }

                // Check if this is regular hours
                if ($classification->is_regular) {
                    $data['regular_hours'] += $hours;

                    continue;
                }

                // Check if this is overtime hours
                if ($classification->is_overtime) {
                    $data['overtime_hours'] += $hours;

                    continue;
                }

                // Check if has ADP code (Hours 3)
                if ($classification->adp_code) {
                    $data['hours_3'][] = [
                        'code' => $classification->adp_code,
                        'amount' => $hours,
                    ];

                    continue;
                }

                // Check classification map for codes
                $code = $classificationMap[$classification->code] ?? null;
                if ($code) {
                    $data['hours_3'][] = [
                        'code' => $code,
                        'amount' => $hours,
                    ];
                }
            }

            return $data;
        })->filter(function ($data) {
            // Only include employees with hours or an external_id
            return $data['file_number'] !== null;
        })->values();
    }

    /**
     * Get mapping of classification codes to ADP codes
     */
    protected function getClassificationMap(): array
    {
        return Classification::whereNotNull('adp_code')
            ->pluck('adp_code', 'code')
            ->toArray();
    }

    /**
     * Generate the ADP-formatted CSV file
     */
    protected function generateAdpCsv(
        Collection $employeeData,
        string $fileName,
        string $companyCode,
        PayPeriod $payPeriod
    ): string {
        $path = 'payroll_exports/'.$fileName;

        // Generate batch ID in YYMMDD format
        $batchId = $payPeriod->end_date->format('ymd');

        // Build CSV content
        $lines = [];

        // Add header row
        $lines[] = implode(',', self::HEADERS);

        // Add employee rows
        foreach ($employeeData as $data) {
            $row = $this->buildEmployeeRow($data, $companyCode, $batchId);
            $lines[] = implode(',', $row);
        }

        $content = implode("\n", $lines)."\n";

        Storage::disk('local')->put($path, $content);

        return Storage::disk('local')->path($path);
    }

    /**
     * Build a single employee row for ADP format
     *
     * @param array{
     *     file_number: string|null,
     *     temp_dept: string|null,
     *     temp_rate: float|null,
     *     regular_hours: float,
     *     overtime_hours: float,
     *     hours_3: array<array{code: string, amount: float}>,
     *     earnings_3: array<array{code: string, amount: float}>
     * } $data
     */
    protected function buildEmployeeRow(array $data, string $companyCode, string $batchId): array
    {
        $row = [
            $companyCode,                                                // Co Code
            $batchId,                                                    // Batch ID
            $data['file_number'],                                        // File #
            $data['temp_dept'] ?? '',                                    // Temp Dept
            $data['temp_rate'] ? $this->formatDecimal($data['temp_rate']) : '',  // Temp Rate
            $this->formatDecimal($data['regular_hours']),                // Reg Hours
            $this->formatDecimal($data['overtime_hours']),               // O/T Hours
        ];

        // Add Hours 3 pairs (up to 3 pairs = 6 columns)
        $hours3 = array_slice($data['hours_3'], 0, 3);
        for ($i = 0; $i < 3; $i++) {
            if (isset($hours3[$i])) {
                $row[] = $hours3[$i]['code'];
                $row[] = $this->formatDecimal($hours3[$i]['amount']);
            } else {
                $row[] = '';
                $row[] = '';
            }
        }

        // Add Earnings 3 pairs (up to 3 pairs = 6 columns)
        $earnings3 = array_slice($data['earnings_3'] ?? [], 0, 3);
        for ($i = 0; $i < 3; $i++) {
            if (isset($earnings3[$i])) {
                $row[] = $earnings3[$i]['code'];
                $row[] = $this->formatDecimal($earnings3[$i]['amount']);
            } else {
                $row[] = '';
                $row[] = '';
            }
        }

        // Add Memo Code and Amount (empty by default)
        $row[] = '';
        $row[] = '';

        return $row;
    }

    /**
     * Format decimal value to 2 decimal places with leading space for positive numbers
     */
    protected function formatDecimal(float $value): string
    {
        // ADP format shows positive numbers with leading space: " 80.00"
        return sprintf(' %.2f', $value);
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

        Log::info("[AdpExport] File copied to: {$destPath}");
    }

    /**
     * Export with progress tracking (called from job)
     */
    public function exportWithProgress(
        PayPeriod $payPeriod,
        IntegrationConnection $provider,
        PayrollExport $export
    ): PayrollExport {
        Log::info("[AdpExport] Starting ADP export with progress for PayPeriod {$payPeriod->id}");

        $companyCode = $provider->adp_company_code ?? 'ADP';
        $fileName = $this->generateFileName($companyCode);

        // Update export with file name
        $export->update(['file_name' => $fileName]);

        try {
            // Step 1: Get employee data
            $export->updateProgress(20, 'Fetching employee data...');
            $employeeData = $this->getEmployeeData($payPeriod, $provider);

            if ($employeeData->isEmpty()) {
                $export->markFailed('No employees assigned to this payroll provider');

                // Also update any related SystemTask directly
                \App\Models\SystemTask::where('related_model', PayrollExport::class)
                    ->where('related_id', $export->id)
                    ->where('status', \App\Models\SystemTask::STATUS_PROCESSING)
                    ->update([
                        'status' => \App\Models\SystemTask::STATUS_FAILED,
                        'error_message' => 'No employees assigned to this payroll provider',
                        'completed_at' => now(),
                    ]);

                return $export;
            }

            $export->update(['total_employees' => $employeeData->count()]);

            // Step 2: Generate CSV with progress
            $export->updateProgress(40, 'Generating ADP CSV...');
            $filePath = $this->generateAdpCsvWithProgress($employeeData, $fileName, $companyCode, $payPeriod, $export);

            // Step 3: Handle destination
            $export->updateProgress(90, 'Saving file...');
            if ($provider->export_destination === 'path' && $provider->export_path) {
                $this->moveToPath($filePath, $provider->export_path, $fileName);
                $export->update(['file_path' => $provider->export_path.'/'.$fileName]);
            } else {
                $export->update(['file_path' => $filePath]);
            }

            // Complete
            $export->updateProgress(100, 'Export complete!');
            $export->markCompleted($employeeData->count(), $employeeData->count());

            Log::info("[AdpExport] Export completed: {$fileName}");

        } catch (\Exception $e) {
            Log::error("[AdpExport] Export failed: {$e->getMessage()}");
            $export->markFailed($e->getMessage());
        }

        return $export;
    }

    /**
     * Generate the ADP-formatted CSV file with progress tracking
     */
    protected function generateAdpCsvWithProgress(
        Collection $employeeData,
        string $fileName,
        string $companyCode,
        PayPeriod $payPeriod,
        PayrollExport $export
    ): string {
        $path = 'payroll_exports/'.$fileName;
        $batchId = $payPeriod->end_date->format('ymd');

        $lines = [];
        $lines[] = implode(',', self::HEADERS);

        $total = $employeeData->count();
        $processed = 0;

        foreach ($employeeData as $data) {
            $row = $this->buildEmployeeRow($data, $companyCode, $batchId);
            $lines[] = implode(',', $row);

            $processed++;
            if ($processed % 25 === 0 || $processed === $total) {
                $progress = 40 + (int) (($processed / $total) * 50);
                $export->updateProgress($progress, "Processing employee {$processed} of {$total}...", $processed);
            }
        }

        $content = implode("\n", $lines)."\n";
        Storage::disk('local')->put($path, $content);

        return Storage::disk('local')->path($path);
    }

    /**
     * Preview export data without generating file
     *
     * @return array<array<string, mixed>>
     */
    public function preview(PayPeriod $payPeriod, IntegrationConnection $provider): array
    {
        $employeeData = $this->getEmployeeData($payPeriod, $provider);

        return $employeeData->map(function ($data) {
            return [
                'file_number' => $data['file_number'],
                'regular_hours' => $data['regular_hours'],
                'overtime_hours' => $data['overtime_hours'],
                'additional_hours' => collect($data['hours_3'])->map(function ($h) {
                    return "{$h['code']}: {$h['amount']}";
                })->implode(', '),
            ];
        })->toArray();
    }
}
