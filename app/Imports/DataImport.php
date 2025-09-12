<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ShiftSchedule;
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

                // Default the is_manual field to true if it is empty
                if (!isset($row['is_manual']) || $row['is_manual'] === '') {
                    $row['is_manual'] = true;
                    Log::info("Defaulted is_manual to true for row {$index}");
                }

                // Filter data to include only fillable fields
                $data = array_intersect_key($row->toArray(), array_flip((new $this->modelClass())->getFillable()));
                
                // Always preserve ID for imports, even if it's not fillable
                if (isset($row['id']) && !empty($row['id'])) {
                    $data['id'] = $row['id'];
                    Log::info("Row {$index} - Added ID back to filtered data: {$row['id']}");
                }
                
                Log::info("Row {$index} - Filtered data (fillable fields only): ", $data);

                // Handle external_department_id mapping to department_id
                if (isset($row['external_department_id'])) {
                    $mappedDepartment = Department::where('external_department_id', $row['external_department_id'])->first();
                    $data['department_id'] = $mappedDepartment->id ?? null;
                    if ($mappedDepartment) {
                        Log::info("Row {$index} - Mapped external_department_id {$row['external_department_id']} to department_id: {$mappedDepartment->id}");
                    } else {
                        Log::warning("Row {$index} - No department found for external_department_id: {$row['external_department_id']}");
                    }
                }

                // Conditionally apply Employee-specific logic
                if ($this->modelClass === Employee::class) {
                    // Lookup and map employee_external_id to employee_id
                    if (isset($data['employee_external_id'])) {
                        $mappedEmployee = Employee::where('external_id', $data['employee_external_id'])->first();

                        if ($mappedEmployee) {
                            $data['employee_id'] = $mappedEmployee->id;
                            $data['employee_name'] = $mappedEmployee->full_name;
                            $data['department_name'] = optional($mappedEmployee->department)->name;
                        } else {
                            throw new \Exception("No employee found for external_id {$data['employee_external_id']}.");
                        }
                    }
                }

                // Validate the data if rules exist
                $model = new $this->modelClass();
                $validatedData = method_exists($model, 'rules')
                    ? Validator::make($data, $model->rules())->validate()
                    : $data;

                // Log final validated data
                Log::info("Row {$index} - Final data being saved to {$this->modelClass}: ", $validatedData);

                // Create or update the model with improved matching logic
                $uniqueKey = $this->determineUniqueKey($validatedData);
                Log::info("Row {$index} - Using unique key: ", $uniqueKey);
                Log::info("Row {$index} - Attempting updateOrCreate with data: ", $validatedData);
                
                $result = $model::updateOrCreate($uniqueKey, $validatedData);
                Log::info("Row {$index} - UpdateOrCreate result ID: {$result->id}, was recently created: " . ($result->wasRecentlyCreated ? 'yes' : 'no'));

                Log::info("Successfully imported/updated row {$index}: ", $data);
            } catch (\Exception $e) {
                Log::error("Failed to import row {$index}: " . json_encode($row->toArray()) . " Error: " . $e->getMessage());
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

        // Define acceptable formats
        $formats = [
            'Y-m-d H:i:s', 'Y-m-d H:i', 'm/d/Y g:i A', 'm/d/Y H:i:s', 'm/d/Y',
            'Y-m-d', 'Y/m/d H:i:s', 'Y/m/d H:i', 'd-m-Y H:i:s', 'd-m-Y H:i',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                return $date->format('Y-m-d H:i:s'); // Standardize format
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
}
