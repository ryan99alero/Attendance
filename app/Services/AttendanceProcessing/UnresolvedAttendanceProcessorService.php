<?php

namespace App\Services\AttendanceProcessing;

use App\Services\Shift\ShiftScheduleService;  // ✅ Import correctly
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\CompanySetup;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UnresolvedAttendanceProcessorService
{
    protected ShiftScheduleService $shiftScheduleService;
    // protected MlPunchTypePredictorService $mlPunchTypePredictorService;

    public function __construct(ShiftScheduleService $shiftScheduleService /*, MlPunchTypePredictorService $mlPunchTypePredictorService */)
    {
        $this->shiftScheduleService = $shiftScheduleService;
        // $this->mlPunchTypePredictorService = $mlPunchTypePredictorService;
    }
    public function processStalePartialRecords(PayPeriod $payPeriod): void
    {
        Log::info("🛠 [processStalePartialRecords] Starting processing for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if (Carbon::today()->equalTo($endDate->toDateString())) {
            Log::info("⚠️ Adjusting end date: Subtracting a day.");
            $endDate = $endDate->subDay();
        }

        $flexibility = CompanySetup::first()->attendance_flexibility_minutes ?? 30;

        Log::info("📆 Processing attendance records between {$startDate} and {$endDate}");

        $staleAttendances = Attendance::where('status', 'Partial')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereNull('punch_type_id')
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get();

        Log::info("📊 Found " . $staleAttendances->count() . " stale records.");

        foreach ($staleAttendances as $punch) {
            Log::info("🔍 Processing Punch ID: {$punch->id} for Employee ID: {$punch->employee_id}");

            // ✅ **Use Shift_Schedule-based logic first**
            $attendanceGroup = DB::table('attendance_time_groups')
                ->where('employee_id', $punch->employee_id)
                ->where('shift_date', $punch->shift_date)
                ->first();

            if ($attendanceGroup) {
                $this->assignPunchTypeUsingShiftSchedule($punch, $attendanceGroup, $flexibility);
            } else {
                Log::warning("⚠️ No Shift Schedule found for Employee ID: {$punch->employee_id} on Shift Date: {$punch->shift_date}. Skipping.");

                // 🔴 **ML Model (commented out until activation)**
                // Log::info("🔍 Predicting Punch Type using ML Model...");
                // $predictedPunchType = $this->mlPunchTypePredictorService->predictPunchType(
                //     $punch->employee_id,
                //     $punch->punch_time
                // );
                //
                // if ($predictedPunchType) {
                //     Log::info("✅ ML Assigned Punch Type ID {$predictedPunchType} to Punch ID: {$punch->id}");
                //     $punch->punch_type_id = $predictedPunchType;
                //     $punch->status = 'NeedsReview';
                //     $punch->issue_notes = 'Auto-assigned via ML Model';
                //     $punch->save();
                // } else {
                //     Log::warning("❌ ML Model could not determine Punch Type for Punch ID: {$punch->id}");
                // }
            }
        }

        Log::info("✅ [processStalePartialRecords] Completed Unresolved Attendance Processing.");
    }

    private function assignPunchTypeUsingShiftSchedule($punch, $attendanceGroup, $flexibility): void
    {
        $shiftStart = Carbon::parse($attendanceGroup->shift_window_start);
        $shiftEnd = Carbon::parse($attendanceGroup->shift_window_end);
        $lunchStart = $attendanceGroup->lunch_start_time ? Carbon::parse($attendanceGroup->lunch_start_time) : null;
        $lunchEnd = $attendanceGroup->lunch_end_time ? Carbon::parse($attendanceGroup->lunch_end_time) : null;
        $punchTime = Carbon::parse($punch->punch_time);

        Log::info("🔍 Evaluating Punch ID: {$punch->id} | Time: {$punchTime} | Shift: {$shiftStart} - {$shiftEnd}");

        // **Mark punch as NeedsReview if outside shift window**
        if ($punchTime->lessThan($shiftStart) || $punchTime->greaterThan($shiftEnd)) {
            Log::warning("⚠️ Punch ID: {$punch->id} falls outside the shift window. Marking as NeedsReview.");
            $punch->status = 'NeedsReview';
            $punch->issue_notes = 'Punch falls outside expected shift window';
            $punch->save();
            return;
        }

        // **Determine Punch Type based on Shift Schedule**
        if ($punchTime->between($shiftStart->subMinutes($flexibility), $shiftStart->addMinutes($flexibility))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Clock In');
        } elseif ($punchTime->between($shiftEnd->subMinutes($flexibility), $shiftEnd->addMinutes($flexibility))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Clock Out');
        } elseif ($lunchStart && $punchTime->between($lunchStart->subMinutes(10), $lunchStart->addMinutes(10))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Lunch Start');
        } elseif ($lunchEnd && $punchTime->between($lunchEnd->subMinutes(10), $lunchEnd->addMinutes(10))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Lunch Stop');
        } else {
            // 🔴 **Fallback: Use Heuristic Logic (commented out until activation)**
            // Log::info("📌 Assigning punch types using heuristic-based logic.");
            //
            // $heuristicPunchType = $this->shiftScheduleService->heuristicPunchTypeAssignment(
            //     $punch->employee_id,
            //     $punch->punch_time
            // );
            //
            // if ($heuristicPunchType) {
            //     $punch->punch_type_id = $this->getPunchTypeIdByName($heuristicPunchType);
            //     Log::info("✅ Assigned Punch Type ID {$punch->punch_type_id} to Punch ID: {$punch->id} using Heuristics.");
            // } else {
            //     Log::warning("❌ Could not determine Punch Type for Punch ID: {$punch->id} using Heuristics.");
            // }

            Log::info("🔄 Fallback assigned Punch Type ID: {$punch->punch_type_id} to Punch ID: {$punch->id}.");
        }

        // **Update punch record**
        $punch->status = 'NeedsReview';
        $punch->issue_notes = 'Auto-assigned via Shift Schedule';
        $punch->save();

        Log::info("✅ Assigned Punch Type ID {$punch->punch_type_id} to Punch ID: {$punch->id}.");
    }
    private function getPunchTypeIdByName(string $punchTypeName): ?int
    {
        return DB::table('punch_types')->where('name', $punchTypeName)->value('id');
    }
}
