<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\ShiftSchedule;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UnresolvedAttendanceProcessorService
{
    protected ShiftScheduleService $shiftScheduleService;

    /**
     * Constructor to inject dependencies.
     *
     * @param ShiftScheduleService $shiftScheduleService
     */
    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
    }

    /**
     * Process all unresolved (stale) attendance records within a given PayPeriod.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function processStalePartialRecords(PayPeriod $payPeriod): void
    {
        Log::info("Starting Unresolved Attendance Processing for PayPeriod ID: {$payPeriod->id} ({$payPeriod->start_date} - {$payPeriod->end_date})");

        // Fetch stale partial records within the PayPeriod
        $staleAttendances = Attendance::where('status', 'Partial')
            ->whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date]) // Filter by PayPeriod
            ->whereDate('punch_time', '<', Carbon::yesterday()->toDateString()) // Ensure at least 1 day has passed
            ->whereNull('punch_type_id') // Only process records missing a punch type
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy(['employee_id', fn ($record) => Carbon::parse($record->punch_time)->toDateString()]);

        Log::info("Found " . $staleAttendances->count() . " employees with unresolved attendance issues in PayPeriod ID: {$payPeriod->id}.");

        foreach ($staleAttendances as $employeeId => $dailyPunches) {
            foreach ($dailyPunches as $date => $punches) {
                $this->processEmployeePunches($employeeId, $date, $punches);
            }
        }

        Log::info("Completed Unresolved Attendance Processing for PayPeriod ID: {$payPeriod->id}.");
    }

    /**
     * Process punches for an employee on a specific date.
     *
     * @param int $employeeId
     * @param string $date
     * @param \Illuminate\Support\Collection $punches
     * @return void
     */
    private function processEmployeePunches(int $employeeId, string $date, $punches): void
    {
        Log::info("Processing Employee ID: {$employeeId} on Date: {$date} with " . count($punches) . " punches.");

        $schedule = $this->shiftScheduleService->getShiftScheduleForEmployee($employeeId);

        if (!$schedule) {
            Log::warning("No shift schedule found for Employee ID: {$employeeId}. Skipping...");
            return;
        }

        $sortedPunches = $punches->sortBy('punch_time')->values();

        // Apply refined shift matching logic
        $this->assignPunchTypesWithTolerance($sortedPunches, $schedule);
    }

    /**
     * Assign punch types using shift-based logic with tolerance checks.
     *
     * @param \Illuminate\Support\Collection $punches
     * @param ShiftSchedule $schedule
     * @return void
     */
    private function assignPunchTypesWithTolerance($punches, ShiftSchedule $schedule): void
    {
        Log::info("Assigning punch types using shift-based logic with tolerance on schedule: {$schedule->id}");

        $punchTimes = $punches->pluck('punch_time')->map(fn($time) => Carbon::parse($time)->format('H:i:s'))->toArray();
        Log::info("Detected punch times: " . implode(", ", $punchTimes));

        foreach ($punches as $punch) {
            $predictedPunchType = $this->predictPunchTypeWithTolerance($punch->punch_time, $schedule);

            if ($predictedPunchType) {
                $punch->punch_type_id = $predictedPunchType;
                $punch->status = 'NeedsReview';
                $punch->issue_notes = 'Auto-assigned via Shift Matching with Tolerance';
                $punch->save();

                Log::info("Assigned Punch Type {$predictedPunchType} to Punch ID: {$punch->id}");
            } else {
                Log::warning("Could not determine punch type for Punch ID: {$punch->id}. Marking as Partial.");
            }
        }
    }

    /**
     * Predict punch type based on shift schedule with tolerance logic.
     *
     * @param string $punchTime
     * @param ShiftSchedule $schedule
     * @return int|null
     */
    private function predictPunchTypeWithTolerance(string $punchTime, ShiftSchedule $schedule): ?int
    {
        $punchSeconds = Carbon::parse($punchTime)->secondsSinceMidnight();

        $times = [
            'Clock In' => Carbon::parse($schedule->start_time)->secondsSinceMidnight(),
            'Lunch Start' => Carbon::parse($schedule->lunch_start_time)->secondsSinceMidnight(),
            'Lunch Stop' => Carbon::parse($schedule->lunch_start_time)->addMinutes($schedule->lunch_duration)->secondsSinceMidnight(),
            'Clock Out' => Carbon::parse($schedule->end_time)->secondsSinceMidnight(),
        ];

        $closestType = null;
        $smallestDiff = PHP_INT_MAX;
        $tolerance = 900; // 15-minute tolerance

        foreach ($times as $type => $time) {
            $diff = abs($punchSeconds - $time);
            if ($diff <= $tolerance && $diff < $smallestDiff) {
                $smallestDiff = $diff;
                $closestType = $type;
            }
        }

        if ($closestType) {
            return $this->getPunchTypeId($closestType);
        }
        return null;
    }

    /**
     * Retrieve the punch type ID by name.
     *
     * @param string $type
     * @return int|null
     */
    private function getPunchTypeId(string $type): ?int
    {
        return \DB::table('punch_types')->where('name', $type)->value('id');
    }
}
