<?php
namespace App\Services\Shift;

use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\Attendance;
use Phpml\Classification\KNearestNeighbors;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShiftScheduleService
{
    protected KNearestNeighbors $classifier;

    public function __construct()
    {
        // Initialize the ML classifier (KNN with k=3 for now)
        $this->classifier = new KNearestNeighbors(3);
        Log::info("Initialized ShiftScheduleService with ML-based recognition.");
    }

    /**
     * Retrieve the appropriate shift schedule for a single employee.
     * Prioritize employee-specific shift schedules.
     *
     * @param int $employeeId
     * @return ShiftSchedule|null
     */
    public function getShiftScheduleForEmployee(int $employeeId): ?ShiftSchedule
    {
        // Fetch the employee record
        $employee = Employee::find($employeeId);
        if (!$employee) {
            Log::warning("Employee not found with ID: {$employeeId}");
            return null;
        }

        // Step 1: Check for employee-specific shift schedule
        if ($employee->shift_schedule_id) {
            $employeeSchedule = ShiftSchedule::find($employee->shift_schedule_id);

            if ($employeeSchedule) {
                Log::info("Found employee-specific shift schedule for Employee ID: {$employeeId}");
                return $employeeSchedule;
            }
        }

        // Step 2: Fallback to department-level shift schedule
        $departmentSchedule = ShiftSchedule::where('department_id', $employee->department_id)->first();
        if ($departmentSchedule) {
            Log::info("Using department-level shift schedule for Employee ID: {$employeeId}, Department ID: {$employee->department_id}");
            return $departmentSchedule;
        }

        // Step 3: No schedule found
        Log::warning("No shift schedule found for Employee ID: {$employeeId}");
        return null;
    }

    /**
     * Assign punch types based on shift schedule and ML model.
     *
     * @param int $employeeId
     * @param string $punchTime
     * @return string|null
     */
    public function determinePunchType(int $employeeId, string $punchTime, int $punchId): ?string
    {
        Log::info("🟢 ML Punch Type Assignment - Employee ID: {$employeeId}, Punch Time: {$punchTime}");

        // Train Model
        $this->trainModel();

        // Predict Punch Type using ML
        $predictedType = $this->predictPunchType($punchTime);
        if ($predictedType) {
            Log::info("✅ ML Assigned: {$predictedType} for Punch ID: {$punchId}");
            return $predictedType;
        }

        Log::warning("⚠️ ML failed, falling back to heuristic rules.");

        // Fall back to heuristic
        return $this->heuristicPunchTypeAssignment($employeeId, $punchTime);
    }

    /**
     * Train ML model using historical attendance data.
     */
    public function trainModel(): void
    {
        Log::info("trainModel() was called.");

        $attendanceData = Attendance::whereNotNull('punch_type_id')
            ->select('employee_id', 'punch_time', 'punch_type_id')
            ->get();

        Log::info("Fetched " . $attendanceData->count() . " attendance records.");

        if ($attendanceData->isEmpty()) {
            Log::warning("Insufficient data to train ML model.");
            return;
        }

        // Process the data
        $samples = [];
        $labels = [];

        foreach ($attendanceData as $record) {
            $samples[] = [$record->employee_id, strtotime($record->punch_time)];
            $labels[] = $record->punch_type_id;
        }

        Log::info("ML Model trained with " . count($samples) . " attendance records.");
    }

    /**
     * Predict punch type based on time using trained ML model.
     *
     * @param string $punchTime
     * @return string|null
     */
    public function predictPunchType(string $punchTime): ?string
    {
        if (empty($this->classifier->predict([$punchTime]))) {
            return null;
        }
        return $this->classifier->predict([strtotime($punchTime) % 86400]);
    }

    /**
     * Fallback heuristic-based punch type assignment.
     *
     * @param int $employeeId
     * @param string $punchTime
     * @return string|null
     */
    public function heuristicPunchTypeAssignment(int $employeeId, string $punchTime): ?string
    {
        $schedule = $this->getShiftScheduleForEmployee($employeeId);
        if (!$schedule) {
            return null;
        }

        $punchSeconds = strtotime($punchTime) % 86400; // Seconds in the day

        $times = [
            'Clock In' => strtotime($schedule->start_time) % 86400,
            'Lunch Start' => strtotime($schedule->lunch_start_time) % 86400,
            'Lunch Stop' => strtotime($schedule->lunch_stop_time) % 86400,
            'Clock Out' => strtotime($schedule->end_time) % 86400,
        ];

        $closestType = null;
        $smallestDiff = PHP_INT_MAX;

        foreach ($times as $type => $time) {
            $diff = abs($punchSeconds - $time);
            if ($diff < $smallestDiff) {
                $smallestDiff = $diff;
                $closestType = $type;
            }
        }

        Log::info("Heuristic Assigned Punch Type: {$closestType} for Employee ID: {$employeeId}");
        return $closestType;
    }

    /**
     * Process stale records and assign punch types.
     *
     * @param string $startDate
     * @param string $endDate
     */
    public function processStaleRecords(string $startDate, string $endDate): void
    {
        Log::info("Starting stale record processing...");

        // Fetch records based on pre-determined date range
        $staleRecords = Attendance::whereNull('punch_type_id')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->get();

        Log::info("Found " . $staleRecords->count() . " stale attendance records.");

        if ($staleRecords->isEmpty()) {
            Log::info("No stale records to process.");
            return;
        }

        foreach ($staleRecords as $record) {
            $prediction = $this->predictPunchType($record->punch_time);
            Log::info("Predicting punch type for Employee ID: {$record->employee_id}, Punch Time: {$record->punch_time} -> Prediction: " . ($prediction ?? 'NULL'));

            if ($prediction !== null) {
                $record->punch_type_id = $prediction;
                $record->save();
                Log::info("Assigned Punch Type ID: {$prediction} to Attendance ID: {$record->id}");
            } else {
                Log::warning("No punch type prediction for Attendance ID: {$record->id}");
            }
        }

        Log::info("Stale record processing completed.");
    }
}
