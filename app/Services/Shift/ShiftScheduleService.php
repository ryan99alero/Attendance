<?php

namespace App\Services\Shift;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use Exception;
use Illuminate\Support\Facades\DB;
use Phpml\Classification\KNearestNeighbors;

class ShiftScheduleService
{
    protected KNearestNeighbors $classifier;

    protected array $punchTypeCache = [];

    protected array $punchTypeNameCache = [];

    protected array $employeeScheduleCache = [];

    public function __construct()
    {
        $this->classifier = new KNearestNeighbors(3);
        $this->cachePunchTypes();
    }

    protected function cachePunchTypes(): void
    {
        $punchTypes = DB::table('punch_types')->get();
        foreach ($punchTypes as $punchType) {
            $this->punchTypeCache[$punchType->name] = $punchType->id;
            $this->punchTypeNameCache[$punchType->id] = $punchType->name;
        }
    }

    /**
     * Retrieve the appropriate shift schedule for a single employee (cached).
     */
    public function getShiftScheduleForEmployee(int $employeeId): ?ShiftSchedule
    {
        // Return from cache if available
        if (isset($this->employeeScheduleCache[$employeeId])) {
            return $this->employeeScheduleCache[$employeeId];
        }

        $employee = Employee::find($employeeId);
        if (! $employee) {
            $this->employeeScheduleCache[$employeeId] = null;

            return null;
        }

        if ($employee->shift_schedule_id) {
            $schedule = ShiftSchedule::find($employee->shift_schedule_id);
            $this->employeeScheduleCache[$employeeId] = $schedule;

            return $schedule;
        }

        $this->employeeScheduleCache[$employeeId] = null;

        return null;
    }

    /**
     * Assign punch types based on shift schedule and ML model.
     */
    public function determinePunchType(int $employeeId, string $punchTime, int $punchId, &$punchEvaluations): void
    {
        $predictedType = $this->predictPunchType($punchTime);
        if ($predictedType) {
            $punchEvaluations[$punchId]['ml'] = [
                'punch_type_id' => $predictedType,
                'punch_state' => $this->determinePunchState($predictedType),
                'source' => 'ML Model',
            ];
        }

        $heuristicType = $this->heuristicPunchTypeAssignment($employeeId, $punchTime);
        if ($heuristicType) {
            $punchEvaluations[$punchId]['heuristic'] = [
                'punch_type_id' => $heuristicType,
                'punch_state' => $this->determinePunchState($heuristicType),
                'source' => 'Heuristic Model',
            ];
        }
    }

    /**
     * Predict punch type based on time using trained ML model.
     */
    public function predictPunchType(string $punchTime): ?int
    {
        try {
            return $this->classifier->predict([strtotime($punchTime) % 86400]);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Fallback heuristic-based punch type assignment.
     */
    public function heuristicPunchTypeAssignment(int $employeeId, string $punchTime): ?int
    {
        $schedule = $this->getShiftScheduleForEmployee($employeeId);
        if (! $schedule) {
            return null;
        }

        $punchSeconds = strtotime($punchTime) % 86400;

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

        return $this->getPunchTypeId($closestType);
    }

    private function determinePunchState(int $punchTypeId): string
    {
        $startTypes = ['Clock In', 'Lunch Start', 'Shift Start', 'Manual Start'];
        $stopTypes = ['Clock Out', 'Lunch Stop', 'Shift Stop', 'Manual Stop'];

        $punchTypeName = $this->punchTypeNameCache[$punchTypeId] ?? null;

        if (in_array($punchTypeName, $startTypes)) {
            return 'start';
        } elseif (in_array($punchTypeName, $stopTypes)) {
            return 'stop';
        }

        return 'unknown';
    }

    private function getPunchTypeId(string $type): ?int
    {
        return $this->punchTypeCache[$type] ?? null;
    }

    /**
     * Process stale records and assign punch types.
     */
    public function processStaleRecords(string $startDate, string $endDate, &$punchEvaluations): void
    {
        DB::disableQueryLog();

        // Use cursor to avoid loading all records into memory
        foreach (Attendance::whereNull('punch_type_id')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->cursor() as $record) {
            $prediction = $this->predictPunchType($record->punch_time);
            if ($prediction !== null) {
                $punchEvaluations[$record->id]['ml'] = [
                    'punch_type_id' => $prediction,
                    'punch_state' => $this->determinePunchState($prediction),
                    'source' => 'ML Model',
                ];
            }
        }
    }
}
