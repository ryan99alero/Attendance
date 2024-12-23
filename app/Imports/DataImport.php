<?php

namespace App\Imports;

use App\Models\Department; // Import the Department model
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
            Log::warning("TACO: The provided collection is empty. Aborting import.");
            return;
        }

        // Log the raw headers
        $headers = $collection->first()->keys()->toArray();
        Log::info("TACO1: Raw headers from file: " . implode(', ', $headers));

        foreach ($collection as $index => $row) {
            Log::info("Processing raw row {$index}: ", $row->toArray());

            // Standardize the punch_time field
            if (isset($row['punch_time'])) {
                try {
                    $row['punch_time'] = $this->parseDateTime($row['punch_time']);
                } catch (\Exception $e) {
                    Log::error("Failed to parse punch_time for row {$index}: " . $row['punch_time']);
                    continue; // Skip this row
                }
            }

            $data = array_intersect_key($row->toArray(), array_flip((new $this->modelClass())->getFillable()));
            Log::info("Filtered data for row {$index}: ", $data);

            // Lookup and replace department_id
            if (isset($data['department_id'])) {
                try {
                    $mappedDepartment = Department::where('external_department_id', $data['department_id'])->first();

                    if ($mappedDepartment) {
                        Log::info("Mapped external_department_id {$data['department_id']} to department ID {$mappedDepartment->id}.");
                        $data['department_id'] = $mappedDepartment->id; // Replace with actual ID
                    } else {
                        Log::warning("No department found for external_department_id {$data['department_id']}. Setting department_id to null.");
                        $data['department_id'] = null; // Handle missing mappings
                    }
                } catch (\Exception $e) {
                    Log::error("Error during department lookup for row {$index}: " . $e->getMessage());
                    continue; // Skip this row
                }
            }

            // Validate and process the data
            try {
                Log::info("Final data for row {$index}: ", $data);

                $model = new $this->modelClass();

                // Validate the data if rules exist
                if (method_exists($model, 'rules')) {
                    $validatedData = Validator::make($data, $model->rules())->validate();
                    Log::info("Validated data for row {$index}: ", $validatedData);
                } else {
                    $validatedData = $data; // Use raw data if no rules are defined
                }

                // Create or update the model
                $createdOrUpdated = $model::updateOrCreate(
                    ['id' => $data['id'] ?? null], // Match by ID if provided
                    $validatedData
                );

                Log::info("Successfully imported/updated row {$index}: ", $createdOrUpdated->toArray());
            } catch (\Exception $e) {
                Log::error("Failed to import row {$index}: " . json_encode($row) . " Error: " . $e->getMessage());
            }
        }

        Log::info("TACO10: Import completed for model: {$this->modelClass}");
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
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value))
                ->format('Y-m-d H:i');
        }

        // Define acceptable formats
        $formats = [
            'Y-m-d H:i', // 2024-12-20 08:12
            'm/d/Y g:i A', // 12/20/2024 8:12 AM
            'm/d/Y H:i:s', // 12/20/2024 08:12:00
            'Y-m-d', // 2024-12-20
            'm/d/Y', // 12/20/2024
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                return $date->format('Y-m-d H:i'); // Standardize to this format
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
            Log::info("TACO11: Resolved file path: {$filePath}");
            return $filePath;
        } catch (\Exception $e) {
            Log::error("TACO12: Failed to resolve file path for {$fileName}. Error: " . $e->getMessage());
            throw $e;
        }
    }
}
