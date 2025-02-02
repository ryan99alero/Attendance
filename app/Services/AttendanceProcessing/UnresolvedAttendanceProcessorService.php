<?php

namespace App\Services\AttendanceProcessing;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Services\ML\MlPunchTypePredictorService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UnresolvedAttendanceProcessorService
{
    protected ShiftScheduleService $shiftScheduleService;
    protected MlPunchTypePredictorService $mlPunchTypePredictorService;

    public function __construct(ShiftScheduleService $shiftScheduleService, MlPunchTypePredictorService $mlPunchTypePredictorService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
        $this->mlPunchTypePredictorService = $mlPunchTypePredictorService;
    }

    public function processStalePartialRecords(PayPeriod $payPeriod): void
    {
        Log::info("🛠 [processStalePartialRecords] Starting Unresolved Attendance Processing for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if (Carbon::today()->equalTo($endDate->toDateString())) {
            Log::info("⚠️ Adjusting end date: Subtracting a day.");
            $endDate = $endDate->subDay();
        }

        Log::info("📆 [processStalePartialRecords] Processing attendance records between {$startDate} and {$endDate}");

        $staleAttendances = Attendance::where('status', 'Partial')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereNull('punch_type_id')
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get();

        Log::info("📊 [processStalePartialRecords] Found " . $staleAttendances->count() . " stale records.");

        foreach ($staleAttendances as $punch) {
            Log::info("🔍 [processStalePartialRecords] Processing Punch ID: {$punch->id} for Employee ID: {$punch->employee_id}...");

            // ✅ Fetch classification_id instead of entry_method
            $classificationId = DB::table('punches')
                ->where('employee_id', $punch->employee_id)
                ->where('punch_time', $punch->punch_time)
                ->where('is_processed', true)
                ->value('classification_id') ?? null;

            // ✅ Call ML model with updated parameters
            Log::info("🔍 [processStalePartialRecords] Predicting Punch Type for Punch ID: {$punch->id}, Employee ID: {$punch->employee_id}, Punch Time: {$punch->punch_time}, Classification ID: " . ($classificationId ?? 'None'));
            $predictedPunchType = $this->mlPunchTypePredictorService->predictPunchType(
                $punch->employee_id,
                $punch->punch_time,
                $classificationId
            );

            if ($predictedPunchType) {
                Log::info("✅ [processStalePartialRecords] ML Assigned Punch Type ID {$predictedPunchType} to Punch ID: {$punch->id}");
                $punch->punch_type_id = $predictedPunchType;
                $punch->status = 'NeedsReview';
                $punch->issue_notes = 'Auto-assigned via ML Model';
                $punch->save();
            } else {
                Log::warning("❌ [processStalePartialRecords] ML Model could not determine Punch Type for Punch ID: {$punch->id}");
            }
        }

        Log::info("✅ [processStalePartialRecords] Completed Unresolved Attendance Processing.");
    }

    private function assignPunchTypesUsingFallback($punch): void
    {
        Log::info("⚠️ Using fallback methods to determine Punch Type for Punch ID: {$punch->id}");

        $schedule = $this->shiftScheduleService->getShiftScheduleForEmployee($punch->employee_id);
        if ($schedule) {
            Log::info("📌 Assigning Punch Type using Shift Schedule...");
            $this->assignPunchTypesUsingShiftLogic($punch, $schedule);
        } else {
            Log::info("📌 Assigning Punch Type using Heuristic Analysis...");
            $this->assignPunchTypesUsingHeuristics($punch);
        }
    }

    private function assignPunchTypesUsingShiftLogic($punch, $schedule): void
    {
        Log::info("📆 Assigning punch types using shift schedule for Employee ID: {$punch->employee_id}");

        $shiftStart = Carbon::parse($schedule->start_time);
        $shiftEnd = Carbon::parse($schedule->end_time);
        $punchTime = Carbon::parse($punch->punch_time);

        if ($punchTime->equalTo($shiftStart) || $punchTime->lessThan($shiftStart->addMinutes(15))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Clock In');
        } elseif ($punchTime->greaterThan($shiftEnd->subMinutes(15)) && $punchTime->lessThanOrEqualTo($shiftEnd->addMinutes(15))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Clock Out');
        }

        $punch->status = 'NeedsReview';
        $punch->issue_notes = 'Auto-assigned via Shift Schedule';
        $punch->save();

        Log::info("✅ Assigned Punch Type ID {$punch->punch_type_id} to Punch ID: {$punch->id} using Shift Logic.");
    }

    private function assignPunchTypesUsingHeuristics($punch): void
    {
        Log::info("📌 Assigning punch types using heuristic-based logic for Employee ID: {$punch->employee_id}");

        $heuristicPunchType = $this->shiftScheduleService->heuristicPunchTypeAssignment($punch->employee_id, $punch->punch_time);

        if ($heuristicPunchType) {
            $punch->punch_type_id = $this->getPunchTypeIdByName($heuristicPunchType);
            $punch->status = 'NeedsReview';
            $punch->issue_notes = 'Auto-assigned via Heuristic Analysis';
            $punch->save();
            Log::info("✅ Assigned Punch Type ID {$punch->punch_type_id} to Punch ID: {$punch->id} using Heuristics.");
        } else {
            Log::warning("❌ Could not determine Punch Type for Punch ID: {$punch->id} using Heuristics.");
        }
    }

    private function getPunchTypeIdByName(string $punchTypeName): ?int
    {
        return DB::table('punch_types')->where('name', $punchTypeName)->value('id');
    }
}
