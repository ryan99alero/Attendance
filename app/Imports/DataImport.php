<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;

class DataImport implements ToCollection, WithHeadingRow
{
    protected string $modelClass;
    protected array $failedRecords = []; // To track failed rows

    /**
     * Constructor to initialize the model class.
     *
     * @param string $modelClass
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        Log::info("DataImport initialized with model: {$modelClass}");
    }

    /**
     * Process the imported collection of rows.
     *
     * @param Collection $collection
     * @return void
     */
    public function collection(Collection $collection): void
    {
        Log::info("Starting import for model: {$this->modelClass}");

        if ($collection->isEmpty()) {
            Log::warning("The provided collection is empty. Aborting import.");
            return;
        }

        // Log the raw headers
        $headers = $collection->first()->keys()->toArray();
        Log::info("Raw headers from file: " . implode(', ', $headers));

        foreach ($collection as $index => $row) {
            Log::info("Processing raw row {$index}: ", $row->toArray());

            try {
                // Standardize the punch_time field
                if (isset($row['punch_time'])) {
                    $row['punch_time'] = $this->parseDateTime($row['punch_time']);
                }

                // Default the status field to "Incomplete" if it is empty
                if (empty($row['status'])) {
                    $row['status'] = 'Incomplete';
                    Log::info("Defaulted status to 'Incomplete' for row {$index}");
                }

                // Default the is_manual field to true if it is empty
                if (!isset($row['is_manual']) || $row['is_manual'] === '') {
                    $row['is_manual'] = true;
                    Log::info("Defaulted is_manual to true for row {$index}");
                }

                $data = array_intersect_key($row->toArray(), array_flip((new $this->modelClass())->getFillable()));
                Log::info("Filtered data for row {$index}: ", $data);

                // Lookup and replace department_id
                if (isset($data['department_id'])) {
                    $mappedDepartment = Department::where('external_department_id', $data['department_id'])->first();

                    if ($mappedDepartment) {
                        Log::info("Mapped external_department_id {$data['department_id']} to department ID {$mappedDepartment->id}.");
                        $data['department_id'] = $mappedDepartment->id; // Replace with actual ID
                    } else {
                        Log::warning("No department found for external_department_id {$data['department_id']}. Setting department_id to null.");
                        $data['department_id'] = null; // Handle missing mappings
                    }
                }

                // Lookup and map employee_external_id to employee_id
                if (isset($data['employee_external_id'])) {
                    $mappedEmployee = Employee::where('external_id', $data['employee_external_id'])->first();

                    if ($mappedEmployee) {
                        Log::info("Mapped employee_external_id {$data['employee_external_id']} to employee ID {$mappedEmployee->id}.");
                        $data['employee_id'] = $mappedEmployee->id; // Set the employee_id
                    } else {
                        throw new \Exception("No employee found for external_id {$data['employee_external_id']}.");
                    }
                }

                // Validate the data if rules exist
                $model = new $this->modelClass();
                $validatedData = method_exists($model, 'rules')
                    ? Validator::make($data, $model->rules())->validate()
                    : $data;

                // Create or update the model
                $model::updateOrCreate(
                    ['id' => $data['id'] ?? null], // Match by ID if provided
                    $validatedData
                );

                Log::info("Successfully imported/updated row {$index}: ", $data);
            } catch (\Exception $e) {
                // Add failed record to the failedRecords array
                $this->failedRecords[] = [
                    'row' => $index,
                    'data' => $row->toArray(),
                    'error' => $e->getMessage(),
                ];
                Log::error("Failed to import row {$index}: " . json_encode($row->toArray()) . " Error: " . $e->getMessage());
            }
        }

        if (!empty($this->failedRecords)) {
            Log::warning("Import completed with errors. See details below:");

            foreach ($this->failedRecords as $failedRecord) {
                Log::warning(sprintf(
                    "Row %d failed. Error: %s | Data: %s",
                    $failedRecord['row'],
                    $failedRecord['error'],
                    json_encode($failedRecord['data'])
                ));
            }
        } else {
            Log::info("Import completed successfully with no errors.");
        }
    }

    /**
     * Get failed records.
     *
     * @return array
     */
    public function getFailedRecords(): array
    {
        return $this->failedRecords;
    }

    /**
     * Parse and standardize the date and time.
     *
     * @param mixed $value
     * @return string
     */
    private function parseDateTime($value): string
    {
        // Handle Excel serial date format (numeric values)
        if (is_numeric($value)) {
            try {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value))
                    ->format('Y-m-d H:i'); // Standardize format
            } catch (\Exception $e) {
                throw new \Exception("Failed to parse Excel serial date format: {$value}");
            }
        }

        // Define acceptable formats
        $formats = [
            'Y-m-d H:i:s', 'Y-m-d H:i', 'm/d/Y g:i A', 'm/d/Y H:i:s', 'm/d/Y',
            'Y-m-d', 'Y/m/d H:i:s', 'Y/m/d H:i', 'd-m-Y H:i:s', 'd-m-Y H:i',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                return $date->format('Y-m-d H:i'); // Standardize format
            } catch (\Exception $e) {
                // Continue to next format
            }
        }

        throw new \Exception("Invalid date format: {$value}");
    }

    /**
     * Define the path to the uploaded files.
     *
     * @param string $fileName
     * @return string
     */
    public static function resolveFilePath(string $fileName): string
    {
        try {
            $filePath = Storage::disk('public')->path($fileName);
            Log::info("Resolved file path: {$filePath}");
            return $filePath;
        } catch (\Exception $e) {
            Log::error("Failed to resolve file path for {$fileName}. Error: " . $e->getMessage());
            throw $e;
        }
    }
}
