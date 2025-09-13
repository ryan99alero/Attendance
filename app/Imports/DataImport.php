<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class DataImport implements ToCollection, WithHeadingRow
{
    protected string $modelClass;
    protected array $failedRecords = [];
    protected $rowProcessor = null;

    /**
     * Constructor to initialize the model class.
     *
     * @param string $modelClass
     * @param callable|null $rowProcessor Optional custom row processing function
     */
    public function __construct(string $modelClass, $rowProcessor = null)
    {
        $this->modelClass = $modelClass;
        $this->rowProcessor = $rowProcessor;
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
            
            // Debug ID field specifically
            Log::info("Row {$index} - ID field debug: isset=" . (isset($row['id']) ? 'true' : 'false') . 
                     ", empty=" . (empty($row['id']) ? 'true' : 'false') . 
                     ", value='" . ($row['id'] ?? 'NULL') . "'");

            try {
                // Standardize time fields for different models
                $this->standardizeTimeFields($row);
                
                // Standardize datetime fields (created_at, updated_at)
                $this->standardizeDateTimeFields($row);

                // Default the is_manual field to true if it is empty
                if (!isset($row['is_manual']) || $row['is_manual'] === '') {
                    $row['is_manual'] = true;
                    Log::info("Defaulted is_manual to true for row {$index}");
                }

                // Convert to array for processing
                $data = $row->toArray();

                // Apply custom row processor if provided
                if ($this->rowProcessor) {
                    $data = call_user_func($this->rowProcessor, $data);
                }

                // Handle external_department_id mapping (only if it exists)
                if (isset($data['external_department_id']) && !empty($data['external_department_id'])) {
                    $externalDepartmentId = str_pad((string)$data['external_department_id'], 3, '0', STR_PAD_LEFT);
                    $mappedDepartment = Department::where('external_department_id', $externalDepartmentId)->first();
                    if ($mappedDepartment) {
                        $data['department_id'] = $mappedDepartment->id;
                        Log::info("Row {$index} - Mapped external_department_id {$externalDepartmentId} to department_id: {$mappedDepartment->id}");
                    } else {
                        Log::warning("Row {$index} - No department found for external_department_id: {$externalDepartmentId}");
                        $data['department_id'] = null;
                    }
                }

                // Filter data to include only fillable fields
                $filteredData = array_intersect_key($data, array_flip((new $this->modelClass())->getFillable()));
                
                // Always preserve ID for imports, even if it's not fillable
                if (isset($data['id']) && !empty($data['id'])) {
                    $filteredData['id'] = $data['id'];
                    Log::info("Row {$index} - Added ID back to filtered data: {$data['id']}");
                }
                
                // Apply datetime standardization to the filtered data
                $this->standardizeDateTimeFieldsForData($filteredData, $index);

                // Validate the data using dynamic validation rules
                $validationRules = $this->generateValidationRules();
                $validatedData = Validator::make($filteredData, $validationRules)->validate();

                // Log final validated data
                Log::info("Row {$index} - Final data being saved to {$this->modelClass}: ", $validatedData);

                // Create or update the model with improved matching logic
                $uniqueKey = $this->determineUniqueKey($validatedData);
                Log::info("Row {$index} - Using unique key: ", $uniqueKey);
                
                $result = (new $this->modelClass())::updateOrCreate($uniqueKey, $validatedData);
                Log::info("Row {$index} - UpdateOrCreate result ID: {$result->id}, was recently created: " . ($result->wasRecentlyCreated ? 'yes' : 'no'));

                Log::info("Successfully imported/updated row {$index}");
            } catch (\Exception $e) {
                $rowNumber = $index + 1;
                
                // Add the error to failed records for export
                $this->failedRecords[] = array_merge($row->toArray(), [
                    'Row' => $rowNumber,
                    'Error' => $e->getMessage(),
                ]);

                Log::error("Failed to import row {$rowNumber}: " . $e->getMessage());
                Log::debug("Row data: " . json_encode($row->toArray()));
            }
        }

        Log::info("Import completed successfully.");
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
                    ->format('Y-m-d H:i:s'); // Standardize format
            } catch (\Exception $e) {
                throw new \Exception("Failed to parse Excel serial date format: {$value}");
            }
        }

        // Pre-process common 2-digit year patterns to avoid misinterpretation
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\s*(AM|PM))?$/i', $value, $matches)) {
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = (int)$matches[3];
            $hour = (int)$matches[4];
            $minute = (int)$matches[5];
            $second = isset($matches[6]) ? (int)$matches[6] : 0;
            $ampm = isset($matches[7]) ? strtoupper($matches[7]) : '';
            
            // Convert 2-digit year to 4-digit (assume 2000s)
            if ($year < 50) {
                $year += 2000; // 00-49 becomes 2000-2049
            } elseif ($year < 100) {
                $year += 1900; // 50-99 becomes 1950-1999 (unlikely for attendance data)
            }
            
            // Handle AM/PM conversion
            if ($ampm === 'PM' && $hour !== 12) {
                $hour += 12;
            } elseif ($ampm === 'AM' && $hour === 12) {
                $hour = 0;
            }
            
            // Create standardized datetime
            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
        }

        // Define acceptable formats (prioritize 2-digit year formats first)
        $formats = [
            // 2-digit year formats (these should be tried first)
            'n/j/y G:i',        // 9/8/25 5:58 (single digit month/day, 2-digit year, 24-hour)
            'n/j/y g:i A',      // 9/8/25 5:58 AM (single digit month/day, 2-digit year, 12-hour)
            'm/d/y H:i:s',      // 09/08/25 05:58:00
            'm/d/y H:i',        // 09/08/25 05:58
            'm/d/y g:i A',      // 09/08/25 5:58 AM
            'm/d/y',            // 09/08/25
            'd/m/y H:i:s',      // 08/09/25 05:58:00 (day/month/year)
            'd/m/y H:i',        // 08/09/25 05:58
            'y-m-d H:i:s',      // 25-09-08 05:58:00
            'y-m-d H:i',        // 25-09-08 05:58
            
            // 4-digit year formats
            'Y-m-d H:i:s', 'Y-m-d H:i', 'm/d/Y g:i A', 'm/d/Y H:i:s', 'm/d/Y',
            'Y-m-d', 'Y/m/d H:i:s', 'Y/m/d H:i', 'd-m-Y H:i:s', 'd-m-Y H:i',
            'Y-m-d\TH:i:s.u\Z', // ISO 8601 with microseconds and Z timezone (2024-12-31T19:46:24.000000Z)
            'Y-m-d\TH:i:s\Z',   // ISO 8601 without microseconds (2024-12-31T19:46:24Z)
            'Y-m-d\TH:i:s',     // ISO 8601 basic format (2024-12-31T19:46:24)
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                return $date->format('Y-m-d H:i:s'); // Standardize format
            } catch (\Exception $e) {
                // Continue to next format
            }
        }

        // Try Carbon's built-in parsing for ISO 8601 and other formats
        try {
            $date = Carbon::parse($value);
            return $date->format('Y-m-d H:i:s'); // Standardize format
        } catch (\Exception $e) {
            // Final fallback for common patterns
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

    /**
     * Standardize time fields based on model type.
     *
     * @param Collection $row
     * @return void
     */
    private function standardizeTimeFields(Collection $row): void
    {
        // Handle punch_time for attendance/punch records
        if (isset($row['punch_time']) && !empty($row['punch_time'])) {
            $row['punch_time'] = $this->parseDateTime($row['punch_time']);
            Log::info("Standardized punch_time: {$row['punch_time']}");
        }

        // Handle ShiftSchedule time fields
        if ($this->modelClass === 'App\Models\ShiftSchedule') {
            $timeFields = ['start_time', 'end_time', 'lunch_start_time', 'lunch_stop_time'];
            
            foreach ($timeFields as $field) {
                if (isset($row[$field]) && !empty($row[$field])) {
                    try {
                        $row[$field] = $this->parseTimeOnly($row[$field]);
                        Log::info("Standardized {$field}: {$row[$field]}");
                    } catch (\Exception $e) {
                        Log::error("Failed to parse {$field} '{$row[$field]}': " . $e->getMessage());
                        // Don't fail the entire import, just skip this field
                        unset($row[$field]);
                    }
                }
            }
        }

        // Handle other time fields for different models as needed
        // Add more model-specific time field handling here
    }

    /**
     * Standardize datetime fields like created_at, updated_at.
     *
     * @param Collection $row
     * @return void
     */
    private function standardizeDateTimeFields(Collection $row): void
    {
        $dateTimeFields = ['created_at', 'updated_at', 'termination_date'];
        
        foreach ($dateTimeFields as $field) {
            if (isset($row[$field]) && !empty($row[$field])) {
                try {
                    $row[$field] = $this->parseDateTime($row[$field]);
                    Log::info("Standardized {$field}: {$row[$field]}");
                } catch (\Exception $e) {
                    Log::error("Failed to parse {$field} '{$row[$field]}': " . $e->getMessage());
                    // For optional fields like termination_date, we can skip
                    if ($field === 'termination_date') {
                        unset($row[$field]);
                    } else {
                        // For required fields like created_at/updated_at, use current time
                        $row[$field] = now()->format('Y-m-d H:i:s');
                        Log::info("Using current time for {$field}: {$row[$field]}");
                    }
                }
            }
        }
    }

    /**
     * Standardize datetime fields in array format (for validation).
     *
     * @param array $data
     * @param int $index
     * @return void
     */
    private function standardizeDateTimeFieldsForData(array &$data, int $index): void
    {
        $dateTimeFields = ['created_at', 'updated_at', 'termination_date'];
        
        foreach ($dateTimeFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                try {
                    $originalValue = $data[$field];
                    $data[$field] = $this->parseDateTime($data[$field]);
                    Log::info("Row {$index} - Standardized {$field}: '{$originalValue}' -> '{$data[$field]}'");
                } catch (\Exception $e) {
                    Log::error("Row {$index} - Failed to parse {$field} '{$data[$field]}': " . $e->getMessage());
                    // For optional fields like termination_date, we can skip
                    if ($field === 'termination_date') {
                        unset($data[$field]);
                    } else {
                        // For required fields like created_at/updated_at, use current time
                        $data[$field] = now()->format('Y-m-d H:i:s');
                        Log::info("Row {$index} - Using current time for {$field}: {$data[$field]}");
                    }
                }
            }
        }
    }

    /**
     * Parse time-only values (like 8:00 AM, 8:00:00 AM, 08:00, etc.)
     *
     * @param mixed $value
     * @return string
     */
    private function parseTimeOnly($value): string
    {
        if (empty($value)) {
            return '00:00:00';
        }

        // Handle Excel serial time format (decimal values between 0 and 1)
        if (is_numeric($value) && $value >= 0 && $value < 1) {
            try {
                $hours = floor($value * 24);
                $minutes = floor(($value * 24 - $hours) * 60);
                $seconds = floor((($value * 24 - $hours) * 60 - $minutes) * 60);
                return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            } catch (\Exception $e) {
                Log::error("Failed to parse Excel serial time: {$value}");
            }
        }

        // Convert to string and clean up
        $timeString = trim((string)$value);
        
        // Handle common time formats
        $timeFormats = [
            'H:i:s',        // 08:00:00, 13:30:45
            'H:i',          // 08:00, 13:30
            'g:i:s A',      // 8:00:00 AM, 1:30:45 PM
            'g:i A',        // 8:00 AM, 1:30 PM
            'h:i:s A',      // 08:00:00 AM, 01:30:45 PM
            'h:i A',        // 08:00 AM, 01:30 PM
            'G:i:s',        // 8:00:00, 13:30:45 (24-hour without leading zero)
            'G:i',          // 8:00, 13:30 (24-hour without leading zero)
        ];

        foreach ($timeFormats as $format) {
            try {
                $parsedTime = Carbon::createFromFormat($format, $timeString);
                return $parsedTime->format('H:i:s');
            } catch (\Exception $e) {
                // Continue to next format
                continue;
            }
        }

        // Try parsing as a full datetime and extract time
        try {
            $parsedDateTime = Carbon::parse($timeString);
            return $parsedDateTime->format('H:i:s');
        } catch (\Exception $e) {
            // Last resort: try to extract time pattern from string
            if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?/i', $timeString, $matches)) {
                $hours = (int)$matches[1];
                $minutes = (int)$matches[2];
                $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
                $ampm = isset($matches[4]) ? strtoupper($matches[4]) : '';

                // Handle AM/PM conversion
                if ($ampm === 'PM' && $hours !== 12) {
                    $hours += 12;
                } elseif ($ampm === 'AM' && $hours === 12) {
                    $hours = 0;
                }

                return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            }
        }

        Log::error("Could not parse time value: {$timeString}");
        throw new \Exception("Invalid time format: {$timeString}");
    }

    /**
     * Determine the unique key for updateOrCreate based on model type and available data.
     *
     * @param array $data
     * @return array
     */
    private function determineUniqueKey(array $data): array
    {
        Log::info("Determining unique key for {$this->modelClass} with data: ", $data);

        // If ID is provided and not empty, use it as primary key
        if (!empty($data['id'])) {
            Log::info("Using ID as unique key: {$data['id']}");
            return ['id' => $data['id']];
        }

        // Model-specific unique key logic
        switch ($this->modelClass) {
            case 'App\Models\ShiftSchedule':
                // For ShiftSchedules, only use schedule_name if ID is truly not available
                // This allows people to update the schedule name and other fields
                if (!empty($data['schedule_name'])) {
                    Log::info("No ID provided, using schedule_name as unique key for ShiftSchedule: {$data['schedule_name']}");
                    return ['schedule_name' => $data['schedule_name']];
                }
                break;

            case 'App\Models\Employee':
                // For Employees, try external_id first, then email, then first_name + last_name
                if (!empty($data['external_id'])) {
                    Log::info("Using external_id as unique key for Employee: {$data['external_id']}");
                    return ['external_id' => $data['external_id']];
                }
                if (!empty($data['email'])) {
                    Log::info("Using email as unique key for Employee: {$data['email']}");
                    return ['email' => $data['email']];
                }
                if (!empty($data['first_name']) && !empty($data['last_name'])) {
                    Log::info("Using first_name + last_name as unique key for Employee: {$data['first_name']} {$data['last_name']}");
                    return [
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name']
                    ];
                }
                break;

            case 'App\Models\Department':
                // For Departments, use external_department_id first, then name
                if (!empty($data['external_department_id'])) {
                    Log::info("Using external_department_id as unique key for Department: {$data['external_department_id']}");
                    return ['external_department_id' => $data['external_department_id']];
                }
                if (!empty($data['name'])) {
                    Log::info("Using name as unique key for Department: {$data['name']}");
                    return ['name' => $data['name']];
                }
                break;

            default:
                // For other models, try common unique fields
                foreach (['name', 'title', 'code', 'email'] as $field) {
                    if (!empty($data[$field])) {
                        Log::info("Using {$field} as unique key for {$this->modelClass}: {$data[$field]}");
                        return [$field => $data[$field]];
                    }
                }
                break;
        }

        // Fallback: create new record (no matching criteria found)
        Log::warning("No unique key found for {$this->modelClass}, will create new record");
        return ['id' => null];
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
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportFailedRecords(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $tableName = (new $this->modelClass())->getTable();
        $fileName = "{$tableName}_import_errors.xlsx";

        return Excel::download(new class($this->failedRecords) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
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
