<?php

namespace App\Services\TimeGrouping;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\CompanySetup;
use App\Services\Shift\ShiftSchedulePunchTypeAssignmentService;
use App\Services\Heuristic\HeuristicPunchTypeAssignmentService;
use App\Services\ML\MLPunchTypePredictorService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceTimeProcessorService
{
    protected ShiftSchedulePunchTypeAssignmentService $shiftScheduleService;
    protected HeuristicPunchTypeAssignmentService $heuristicService;
    protected MLPunchTypePredictorService $mlService;

    public function __construct(
        ShiftSchedulePunchTypeAssignmentService $shiftScheduleService,
        HeuristicPunchTypeAssignmentService $heuristicService,
        MLPunchTypePredictorService $mlService
    ) {
        $this->shiftScheduleService = $shiftScheduleService;
        $this->heuristicService = $heuristicService;
        $this->mlService = $mlService;
        Log::info("Initialized AttendanceTimeProcessorService.");
    }

    public function processAttendanceForPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("Starting attendance processing for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();
        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        $companySetup = CompanySetup::first();
        $flexibility = $companySetup->attendance_flexibility_minutes ?? 30;

        $attendances = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->where('status', 'Incomplete')
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy('employee_id');

        Log::info("📌 Found {$attendances->count()} incomplete attendance records for PayPeriod ID: {$payPeriod->id}");

        foreach ($attendances as $employeeId => $punches) {
            Log::info("Processing Employee ID: {$employeeId} with {$punches->count()} punch(es).");

            // Debug Mode Logic
            $debugMode = $companySetup->debug_punch_assignment_mode ?? 'full';

            if ($debugMode === 'shift_schedule' || $debugMode === 'full') {
                $this->shiftScheduleService->assignPunchTypes($punches, $flexibility);
            }

            if ($debugMode === 'heuristic' || $debugMode === 'full') {
                $this->heuristicService->assignPunchTypes($punches, $flexibility);
            }

            if ($debugMode === 'ml' || ($debugMode === 'full' && $companySetup->use_ml_for_punch_matching)) {
                $this->mlService->assignPunchTypes($punches, $employeeId);
            }
        }

        Log::info("Attendance processing completed for PayPeriod ID: {$payPeriod->id}");
    }
}
