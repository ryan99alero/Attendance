<?php

namespace App\Services\AttendanceProcessing;

use App\Services\Shift\ShiftScheduleService;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\CompanySetup;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UnresolvedAttendanceProcessorService
{
    protected ShiftScheduleService $shiftScheduleService;

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
    }

    public function processStalePartialRecords(PayPeriod $payPeriod): void
    {
        Log::info("[UnresolvedProcessor] 🛠 Starting processing for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if (Carbon::today()->equalTo($endDate->toDateString())) {
            Log::info("[UnresolvedProcessor] ⚠️ Adjusting end date: Subtracting a day.");
            $endDate = $endDate->subDay();
        }

        $flexibility = CompanySetup::first()->attendance_flexibility_minutes ?? 30;

        Log::info("[UnresolvedProcessor] 📆 Processing attendance records between {$startDate} and {$endDate}");

        $staleAttendances = Attendance::where('status', 'Partial')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereNull('punch_type_id')
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get();

        Log::info("[UnresolvedProcessor] 📊 Found " . $staleAttendances->count() . " stale records.");

        foreach ($staleAttendances as $punch) {
            Log::info("[UnresolvedProcessor] 🔍 Processing Punch ID: {$punch->id} for Employee ID: {$punch->employee_id}");

            $attendanceGroup = DB::table('attendance_time_groups')
                ->where('employee_id', $punch->employee_id)
                ->where('shift_date', $punch->shift_date)
                ->first();

            if ($attendanceGroup) {
                $this->assignPunchTypeUsingShiftSchedule($punch, $attendanceGroup, $flexibility);
            } else {
                Log::warning("[UnresolvedProcessor] ⚠️ No Shift Schedule found for Employee ID: {$punch->employee_id} on Shift Date: {$punch->shift_date}. Marking as NeedsReview.");

                $punch->status = 'NeedsReview';
                $punch->issue_notes = 'No Shift Schedule found';
                $punch->save();
            }
        }

        Log::info("[UnresolvedProcessor] ✅ Completed Unresolved Attendance Processing.");
    }

    private function assignPunchTypeUsingShiftSchedule($punch, $attendanceGroup, $flexibility): void
    {
        $shiftStart = Carbon::parse($attendanceGroup->shift_window_start);
        $shiftEnd = Carbon::parse($attendanceGroup->shift_window_end);
        $lunchStart = $attendanceGroup->lunch_start_time ? Carbon::parse($attendanceGroup->lunch_start_time) : null;
        $lunchEnd = $attendanceGroup->lunch_end_time ? Carbon::parse($attendanceGroup->lunch_end_time) : null;
        $punchTime = Carbon::parse($punch->punch_time);

        Log::info("[UnresolvedProcessor] 🔍 Evaluating Punch ID: {$punch->id} | Time: {$punchTime} | Shift: {$shiftStart} - {$shiftEnd}");

        // Mark punch as NeedsReview if outside shift window
        if ($punchTime->lessThan($shiftStart) || $punchTime->greaterThan($shiftEnd)) {
            Log::warning("[UnresolvedProcessor] ⚠️ Punch ID: {$punch->id} falls outside the shift window.");
            $punch->status = 'NeedsReview';
            $punch->issue_notes = 'Punch falls outside expected shift window';
            $punch->save();
            return;
        }

        // Assign Punch Type & State
        if ($punchTime->between($shiftStart->subMinutes($flexibility), $shiftStart->addMinutes($flexibility))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Clock In');
            $punch->punch_state = 'start';
        } elseif ($punchTime->between($shiftEnd->subMinutes($flexibility), $shiftEnd->addMinutes($flexibility))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Clock Out');
            $punch->punch_state = 'stop';
        } elseif ($lunchStart && $punchTime->between($lunchStart->subMinutes(10), $lunchStart->addMinutes(10))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Lunch Start');
            $punch->punch_state = 'start';
        } elseif ($lunchEnd && $punchTime->between($lunchEnd->subMinutes(10), $lunchEnd->addMinutes(10))) {
            $punch->punch_type_id = $this->getPunchTypeIdByName('Lunch Stop');
            $punch->punch_state = 'stop';
        } else {
            Log::info("[UnresolvedProcessor] 🔄 Punch ID: {$punch->id} did not match predefined time windows. Marking as NeedsReview.");
            $punch->status = 'NeedsReview';
            $punch->issue_notes = 'Could not determine Punch Type via Shift Schedule';
            $punch->save();
            return;
        }

        // Update punch record
        $punch->status = 'NeedsReview';
        $punch->issue_notes = 'Auto-assigned via Shift Schedule';
        $punch->save();

        Log::info("[UnresolvedProcessor] ✅ Assigned Punch Type ID {$punch->punch_type_id} (State: {$punch->punch_state}) to Punch ID: {$punch->id}.");
    }

    private function getPunchTypeIdByName(string $punchTypeName): ?int
    {
        return DB::table('punch_types')->where('name', $punchTypeName)->value('id');
    }
}
