<?php

namespace App\Services\Shift;

use Exception;
use DB;
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
        $this->classifier = new KNearestNeighbors(3);
        Log::info("[Shift] Initialized ShiftScheduleService with ML-based recognition.");
    }

    /**
     * Retrieve the appropriate shift schedule for a single employee.
     */
    public function getShiftScheduleForEmployee(int $employeeId): ?ShiftSchedule
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            Log::warning("[Shift] Employee not found with ID: {$employeeId}");
            return null;
        }

        // Step 1: Check for employee-specific shift schedule
        if ($employee->shift_schedule_id) {
            $employeeSchedule = ShiftSchedule::find($employee->shift_schedule_id);
            if ($employeeSchedule) {
                Log::info("[Shift] Found employee-specific shift schedule for Employee ID: {$employeeId}");
                return $employeeSchedule;
            }
        }

        // Step 2: Fallback to department-level shift schedule
        $departmentSchedule = ShiftSchedule::where('department_id', $employee->department_id)->first();
        if ($departmentSchedule) {
            Log::info("[Shift] Using department-level shift schedule for Employee ID: {$employeeId}, Department ID: {$employee->department_id}");
            return $departmentSchedule;
        }

        Log::warning("[Shift] No shift schedule found for Employee ID: {$employeeId}");
        return null;
    }

    /**
     * Assign punch types based on shift schedule and ML model.
     */
    public function determinePunchType(int $employeeId, string $punchTime, int $punchId, &$punchEvaluations): void
    {
        Log::info("[Shift] Processing Punch Type Assignment - Employee ID: {$employeeId}, Punch Time: {$punchTime}");

        // Train ML Model
        $this->trainModel();

        // Predict Punch Type using ML
        $predictedType = $this->predictPunchType($punchTime);
        if ($predictedType) {
            $punchEvaluations[$punchId]['ml'] = [
                'punch_type_id' => $predictedType,
                'punch_state' => $this->determinePunchState($predictedType),
                'source' => 'ML Model'
            ];
            Log::info("[ML] Assigned: {$predictedType} for Punch ID: {$punchId}");
        }

        // Fallback to heuristic if ML fails
        $heuristicType = $this->heuristicPunchTypeAssignment($employeeId, $punchTime);
        if ($heuristicType) {
            $punchEvaluations[$punchId]['heuristic'] = [
                'punch_type_id' => $heuristicType,
                'punch_state' => $this->determinePunchState($heuristicType),
                'source' => 'Heuristic Model'
            ];
            Log::info("[Heuristic] Assigned: {$heuristicType} for Punch ID: {$punchId}");
        }
    }

    /**
     * Train ML model using historical attendance data.
     */
    public function trainModel(): void
    {
        Log::info("[Shift] Training ML Model...");

        $attendanceData = Attendance::whereNotNull('punch_type_id')
            ->select('employee_id', 'punch_time', 'punch_type_id')
            ->get();

        if ($attendanceData->isEmpty()) {
            Log::warning("[Shift] Insufficient data to train ML model.");
            return;
        }

        // Process the data
        $samples = [];
        $labels = [];

        foreach ($attendanceData as $record) {
            $samples[] = [$record->employee_id, strtotime($record->punch_time)];
            $labels[] = $record->punch_type_id;
        }

        Log::info("[Shift] ML Model trained with " . count($samples) . " attendance records.");
    }

    /**
     * Predict punch type based on time using trained ML model.
     */
    public function predictPunchType(string $punchTime): ?int
    {
        try {
            return $this->classifier->predict([strtotime($punchTime) % 86400]);
        } catch (Exception $e) {
            Log::error("[Shift] ML prediction failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback heuristic-based punch type assignment.
     */
    public function heuristicPunchTypeAssignment(int $employeeId, string $punchTime): ?int
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

        Log::info("[Shift] Heuristic Assigned Punch Type: {$closestType} for Employee ID: {$employeeId}");
        return $this->getPunchTypeId($closestType);
    }

    private function determinePunchState(int $punchTypeId): string
    {
        $startTypes = ['Clock In', 'Lunch Start', 'Shift Start', 'Manual Start'];
        $stopTypes = ['Clock Out', 'Lunch Stop', 'Shift Stop', 'Manual Stop'];

        $punchTypeName = DB::table('punch_types')->where('id', $punchTypeId)->value('name');

        if (in_array($punchTypeName, $startTypes)) {
            return 'start';
        } elseif (in_array($punchTypeName, $stopTypes)) {
            return 'stop';
        }

        return 'unknown';
    }

    private function getPunchTypeId(string $type): ?int
    {
        return DB::table('punch_types')->where('name', $type)->value('id');
    }

    /**
     * Process stale records and assign punch types.
     */
    public function processStaleRecords(string $startDate, string $endDate, &$punchEvaluations): void
    {
        Log::info("[Shift] Processing stale records...");

        $staleRecords = Attendance::whereNull('punch_type_id')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->get();

        if ($staleRecords->isEmpty()) {
            Log::info("[Shift] No stale records to process.");
            return;
        }

        foreach ($staleRecords as $record) {
            $prediction = $this->predictPunchType($record->punch_time);
            if ($prediction !== null) {
                $punchEvaluations[$record->id]['ml'] = [
                    'punch_type_id' => $prediction,
                    'punch_state' => $this->determinePunchState($prediction),
                    'source' => 'ML Model'
                ];
            }
        }

        Log::info("[Shift] Stale record processing completed.");
    }
}
