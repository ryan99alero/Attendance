<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\CompanySetup;
use App\Models\PayPeriod;
use App\Services\Shift\ShiftScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnresolvedAttendanceProcessorService
{
    protected ShiftScheduleService $shiftScheduleService;

    protected ?int $clockInTypeId = null;

    protected ?int $clockOutTypeId = null;

    protected ?int $lunchStartTypeId = null;

    protected ?int $lunchStopTypeId = null;

    protected array $punchTypeDirectionCache = [];

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
    }

    protected function cachePunchTypes(): void
    {
        $punchTypes = DB::table('punch_types')->get(['id', 'name', 'punch_direction']);

        foreach ($punchTypes as $punchType) {
            $this->punchTypeDirectionCache[$punchType->id] = $punchType->punch_direction;

            match ($punchType->name) {
                'Clock In' => $this->clockInTypeId = $punchType->id,
                'Clock Out' => $this->clockOutTypeId = $punchType->id,
                'Lunch Start' => $this->lunchStartTypeId = $punchType->id,
                'Lunch Stop' => $this->lunchStopTypeId = $punchType->id,
                default => null,
            };
        }
    }

    /**
     * Get punch_direction from cache for a given punch type ID.
     */
    protected function getPunchDirection(?int $punchTypeId): string
    {
        return $this->punchTypeDirectionCache[$punchTypeId] ?? 'unknown';
    }

    public function processStalePartialRecords(PayPeriod $payPeriod): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        // Cache punch type IDs
        $this->cachePunchTypes();

        Log::info("[UnresolvedProcessor] Starting processing for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        if (Carbon::today()->equalTo($endDate->toDateString())) {
            $endDate = $endDate->subDay();
        }

        $flexibility = CompanySetup::first()->attendance_flexibility_minutes ?? 30;

        $totalCount = Attendance::where('status', 'Partial')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereNull('punch_type_id')
            ->count();

        Log::info("[UnresolvedProcessor] Found {$totalCount} stale records.");

        $processed = 0;

        // Use cursor() to iterate one record at a time
        foreach (Attendance::where('status', 'Partial')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereNull('punch_type_id')
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->cursor() as $punch) {

            $attendanceGroup = DB::table('attendance_time_groups')
                ->where('employee_id', $punch->employee_id)
                ->where('shift_date', $punch->shift_date)
                ->first();

            if ($attendanceGroup) {
                $this->assignPunchTypeUsingShiftSchedule($punch, $attendanceGroup, $flexibility);
            } else {
                $punch->status = 'NeedsReview';
                $punch->issue_notes = 'No Shift Schedule found';
                $punch->save();
            }

            $processed++;
        }

        Log::info("[UnresolvedProcessor] Completed - Processed {$processed} records.");
    }

    private function assignPunchTypeUsingShiftSchedule($punch, $attendanceGroup, $flexibility): void
    {
        $shiftStart = Carbon::parse($attendanceGroup->shift_window_start);
        $shiftEnd = Carbon::parse($attendanceGroup->shift_window_end);
        $lunchStart = $attendanceGroup->lunch_start_time ? Carbon::parse($attendanceGroup->lunch_start_time) : null;
        $lunchEnd = $attendanceGroup->lunch_end_time ? Carbon::parse($attendanceGroup->lunch_end_time) : null;
        $punchTime = Carbon::parse($punch->punch_time);

        // Mark punch as NeedsReview if outside shift window
        if ($punchTime->lessThan($shiftStart) || $punchTime->greaterThan($shiftEnd)) {
            $punch->status = 'NeedsReview';
            $punch->issue_notes = 'Punch falls outside expected shift window';
            $punch->save();

            return;
        }

        // Assign Punch Type & State (using database-driven punch_direction)
        if ($punchTime->between($shiftStart->subMinutes($flexibility), $shiftStart->addMinutes($flexibility))) {
            $punch->punch_type_id = $this->clockInTypeId;
            $punch->punch_state = $this->getPunchDirection($this->clockInTypeId);
        } elseif ($punchTime->between($shiftEnd->subMinutes($flexibility), $shiftEnd->addMinutes($flexibility))) {
            $punch->punch_type_id = $this->clockOutTypeId;
            $punch->punch_state = $this->getPunchDirection($this->clockOutTypeId);
        } elseif ($lunchStart && $punchTime->between($lunchStart->subMinutes(10), $lunchStart->addMinutes(10))) {
            $punch->punch_type_id = $this->lunchStartTypeId;
            $punch->punch_state = $this->getPunchDirection($this->lunchStartTypeId);
        } elseif ($lunchEnd && $punchTime->between($lunchEnd->subMinutes(10), $lunchEnd->addMinutes(10))) {
            $punch->punch_type_id = $this->lunchStopTypeId;
            $punch->punch_state = $this->getPunchDirection($this->lunchStopTypeId);
        } else {
            $punch->status = 'NeedsReview';
            $punch->issue_notes = 'Could not determine Punch Type via Shift Schedule';
            $punch->save();

            return;
        }

        $punch->status = 'NeedsReview';
        $punch->issue_notes = 'Auto-assigned via Shift Schedule';
        $punch->save();
    }
}
