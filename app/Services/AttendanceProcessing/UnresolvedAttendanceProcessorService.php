<?php

namespace App\Services\AttendanceProcessing;

use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\PayPeriod;
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
        Log::info("🛠 [processStalePartialRecords] Starting Shift Schedule Processing for PayPeriod ID: {$payPeriod->id}");

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

            // ✅ **Use only Shift_Schedule-based logic for now**
            $this->assignPunchTypesUsingShiftLogic($punch);

            // // 🔴 **ML Model is commented out**
            // Log::info("🔍 [processStalePartialRecords] Predicting Punch Type using ML Model...");
            // $predictedPunchType = $this->mlPunchTypePredictorService->predictPunchType(
            //     $punch->employee_id,
            //     $punch->punch_time,
            //     $classificationId
            // );
            //
            // if ($predictedPunchType) {
            //     Log::info("✅ [processStalePartialRecords] ML Assigned Punch Type ID {$predictedPunchType} to Punch ID: {$punch->id}");
            //     $punch->punch_type_id = $predictedPunchType;
            //     $punch->status = 'NeedsReview';
            //     $punch->issue_notes = 'Auto-assigned via ML Model';
            //     $punch->save();
            // } else {
            //     Log::warning("❌ [processStalePartialRecords] ML Model could not determine Punch Type for Punch ID: {$punch->id}");
            // }
        }

        Log::info("✅ [processStalePartialRecords] Completed Unresolved Attendance Processing (Shift Schedule Only).");
    }

    private function assignPunchTypesUsingShiftLogic($punch): void
    {
        Log::info("📆 Assigning punch types using Shift Schedule for Employee ID: {$punch->employee_id}");

        // Fetch shift schedule from attendance_time_groups
        $attendanceGroup = DB::table('attendance_time_groups')
            ->where('employee_id', $punch->employee_id)
            ->where('shift_date', $punch->shift_date)
            ->first();

        if (!$attendanceGroup) {
            Log::warning("⚠️ No Shift Schedule found for Employee ID: {$punch->employee_id} on Shift Date: {$punch->shift_date} - Punch ID: {$punch->id}. Skipping assignment.");
            return;
        }

        $shiftStart = Carbon::parse($attendanceGroup->shift_window_start);
        $shiftEnd = Carbon::parse($attendanceGroup->shift_window_end);
        $lunchStart = $attendanceGroup->lunch_start_time ? Carbon::parse($attendanceGroup->lunch_start_time) : null;
        $lunchEnd = $attendanceGroup->lunch_end_time ? Carbon::parse($attendanceGroup->lunch_end_time) : null;
        $punchTime = Carbon::parse($punch->punch_time);

        Log::info("🔍 Evaluating Punch ID: {$punch->id} | Time: {$punchTime} | Shift: {$shiftStart} - {$shiftEnd}");

        // Check if punch is outside the shift window
        if ($punchTime->lessThan($shiftStart) || $punchTime->greaterThan($shiftEnd)) {
            Log::warning("⚠️ Punch ID: {$punch->id} falls **outside** the shift window. Skipping.");
            return;
        }

        // Determine Punch Type based on Shift Schedule
        if ($punchTime->equalTo($shiftStart) || $punchTime->between($shiftStart, $shiftStart->copy()->addMinutes(15))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Clock In');
        } elseif ($punchTime->between($shiftEnd->copy()->subMinutes(15), $shiftEnd->copy()->addMinutes(15))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Clock Out');
        } elseif ($lunchStart && $lunchEnd && $punchTime->between($lunchStart->copy()->subMinutes(10), $lunchStart->copy()->addMinutes(10))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Lunch Start');
        } elseif ($lunchEnd && $punchTime->between($lunchEnd->copy()->subMinutes(10), $lunchEnd->copy()->addMinutes(10))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Lunch Stop');
        } else {
            // ✅ Fallback Heuristic: If it doesn’t match a strict boundary, classify based on relative time
            $midpoint = $shiftStart->copy()->addMinutes($shiftStart->diffInMinutes($shiftEnd) / 2);
            $preLunch = $lunchStart ? $lunchStart->copy()->subMinutes(30) : null;
            $postLunch = $lunchEnd ? $lunchEnd->copy()->addMinutes(30) : null;

            if ($punchTime->lessThan($midpoint)) {
                $punch->punch_type_id = $this->getPunchTypeIdByName('Clock In');
                Log::info("🔄 Punch ID: {$punch->id} assigned fallback 'Clock In' due to pre-midpoint placement.");
            } elseif ($lunchStart && $punchTime->between($preLunch, $lunchStart)) {
                $punch->punch_type_id = $this->getPunchTypeIdByName('Lunch Start');
                Log::info("🔄 Punch ID: {$punch->id} assigned fallback 'Lunch Start' due to proximity.");
            } elseif ($lunchEnd && $punchTime->between($lunchEnd, $postLunch)) {
                $punch->punch_type_id = $this->getPunchTypeIdByName('Lunch Stop');
                Log::info("🔄 Punch ID: {$punch->id} assigned fallback 'Lunch Stop' due to proximity.");
            } else {
                $punch->punch_type_id = $this->getPunchTypeIdByName('Clock Out');
                Log::info("🔄 Punch ID: {$punch->id} assigned fallback 'Clock Out' due to post-midpoint placement.");
            }
        }

        // Update punch record
        $punch->status = 'NeedsReview';
        $punch->issue_notes = 'Auto-assigned via Shift Schedule';
        $punch->save();

        Log::info("✅ Assigned Punch Type ID {$punch->punch_type_id} to Punch ID: {$punch->id} using Shift Schedule.");
    }

    // 🔴 **Heuristic-based logic is commented out**
    // private function assignPunchTypesUsingHeuristics($punch): void
    // {
    //     Log::info("📌 Assigning punch types using heuristic-based logic for Employee ID: {$punch->employee_id}");
    //
    //     $heuristicPunchType = $this->shiftScheduleService->heuristicPunchTypeAssignment($punch->employee_id, $punch->punch_time);
    //
    //     if ($heuristicPunchType) {
    //         $punch->punch_type_id = $this->getPunchTypeIdByName($heuristicPunchType);
    //         $punch->status = 'NeedsReview';
    //         $punch->issue_notes = 'Auto-assigned via Heuristic Analysis';
    //         $punch->save();
    //         Log::info("✅ Assigned Punch Type ID {$punch->punch_type_id} to Punch ID: {$punch->id} using Heuristics.");
    //     } else {
    //         Log::warning("❌ Could not determine Punch Type for Punch ID: {$punch->id} using Heuristics.");
    //     }
    // }

    private function getPunchTypeIdByName(string $punchTypeName): ?int
    {
        return DB::table('punch_types')->where('name', $punchTypeName)->value('id');
    }
}
