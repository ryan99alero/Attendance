<?php

namespace App\Services;

use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ExcelErrorImportService implements ToCollection, WithHeadingRow
{
    protected $rowProcessor = null;
    protected string $modelClass;
    protected array $failedRecords = [];
    protected array $fillableFields = [];

    /**
     * Initialize the import service.
     *
     * @param string $modelClass
     * @param callable|null $rowProcessor
     */
    public function __construct(string $modelClass, $rowProcessor = null)
    {
        $this->modelClass = $modelClass;
        $this->fillableFields = (new $modelClass())->getFillable();
        $this->rowProcessor = $rowProcessor;
    }

    /**
     * Process the imported collection.
     *
     * @param Collection $collection
     */
    public function collection(Collection $collection): void
    {
        Log::info("Starting import for model: {$this->modelClass}");

        foreach ($collection as $index => $row) {
            try {
                $data = $row->toArray();

                // Ensure specific fields are treated as strings
                if (isset($data['external_employee_id'])) {
                    $data['external_employee_id'] = str_pad((string)$data['external_employee_id'], 3, '0', STR_PAD_LEFT);
                }

                if (isset($data['external_department_id'])) {
                    $externalDepartmentId = str_pad((string)$data['external_department_id'], 3, '0', STR_PAD_LEFT);
                    $data['external_department_id'] = $externalDepartmentId;

                    // Map external_department_id to department_id
                    $mappedDepartment = DB::table('departments')
                        ->where('external_department_id', $externalDepartmentId)
                        ->first();

                    if ($mappedDepartment) {
                        $data['department_id'] = $mappedDepartment->id;
                        Log::info("Mapped external_department_id {$externalDepartmentId} to department_id {$mappedDepartment->id}");
                    } else {
                        Log::warning("No department found for external_department_id: {$externalDepartmentId}. Setting department_id to null.");
                        $data['department_id'] = null;
                    }
                }

                // Custom row processing (e.g., enrich data, handle relationships)
                if ($this->rowProcessor) {
                    $data = call_user_func($this->rowProcessor, $data);
                }

                // Filter data to include only fillable fields
                $filteredData = array_intersect_key($data, array_flip($this->fillableFields));

                // Automatically validate data based on database schema
                $validationRules = $this->generateValidationRules();
                Validator::make($filteredData, $validationRules)->validate();

                // Insert or update the model
                $model = new $this->modelClass();
                $model::updateOrCreate(['id' => $filteredData['id'] ?? null], $filteredData);
            } catch (Exception $e) {
                $rowNumber = $index + 1;

                // Add the error to failed records
                $this->failedRecords[] = array_merge($row->toArray(), [
                    'Row' => $rowNumber,
                    'Error' => $e->getMessage(),
                ]);

                Log::error("Failed to process row {$rowNumber}: " . $e->getMessage());
                Log::debug("Row data: " . json_encode($row->toArray()));
            }
        }
    }

    /**
     * Generate validation rules dynamically based on database schema.
     *
     * @return array
     */
    protected function generateValidationRules(): array
    {
        $table = (new $this->modelClass())->getTable();
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        $rules = [];

        foreach ($columns as $column) {
            $type = DB::getSchemaBuilder()->getColumnType($table, $column);
            $rules[$column] = match ($type) {
                'string', 'text' => 'nullable|string',
                'integer', 'bigint', 'smallint' => 'nullable|integer',
                'decimal', 'float' => 'nullable|numeric',
                'boolean' => 'nullable|boolean',
                'date' => 'nullable|date',
                'datetime', 'timestamp' => 'nullable|date_format:Y-m-d H:i:s',
                default => 'nullable',
            };
        }

        return $rules;
    }

    /**
     * Get the failed records with errors.
     *
     * @return array
     */
    public function getFailedRecords(): array
    {
        return $this->failedRecords;
    }

    /**
     * Export failed records as an Excel file.
     *
     * @return BinaryFileResponse
     */
    public function exportFailedRecords(): BinaryFileResponse
    {
        $tableName = (new $this->modelClass())->getTable();
        $fileName = "{$tableName}_import_errors.xlsx";

        return Excel::download(new class($this->failedRecords) implements FromCollection, WithHeadings {
            private $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function collection(): Collection
            {
                return collect($this->data);
            }

            public function headings(): array
            {
                if (empty($this->data)) {
                    return [];
                }

                return array_keys($this->data[0]);
            }
        }, $fileName);
    }
}
