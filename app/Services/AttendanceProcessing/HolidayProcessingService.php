<?php

namespace App\Services\AttendanceProcessing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\PayPeriod;
use App\Models\Employee;
use Carbon\Carbon;

class HolidayProcessingService
{
    protected ShiftScheduleService $shiftScheduleService;

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
    }

    public function processHolidays(string $startDate, string $endDate): void
    {
        Log::info("🔍 Starting Holiday Processing for period: {$startDate} to {$endDate}");

        $payPeriods = PayPeriod::whereBetween('start_date', [$startDate, $endDate])->get();
        foreach ($payPeriods as $payPeriod) {
            if ($payPeriod->attendanceIssuesCount() > 0) {
                Log::warning("⚠️ PayPeriod ID {$payPeriod->id} has unresolved attendance issues. Skipping holidays.");
                continue;
            }
        }

        $holidays = Holiday::whereBetween('start_date', [$startDate, $endDate])
            ->orWhere(function ($query) use ($startDate, $endDate) {
                $query->where('is_recurring', true)
                    ->whereBetween('start_date', [$startDate, $endDate]);
            })
            ->get();

        Log::info("🔍 Found " . $holidays->count() . " holidays in the specified range.");

        foreach ($holidays as $holiday) {
            $dates = $this->generateHolidayDates($holiday);
            Log::info("📆 Processing Holiday: {$holiday->name} on Dates: " . implode(", ", $dates));

            foreach ($dates as $date) {
                $this->addHolidayToAttendance($holiday, $date);
            }
        }
    }

    private function generateHolidayDates(Holiday $holiday): array
    {
        $dates = [];
        $start = Carbon::parse($holiday->start_date);
        $end = Carbon::parse($holiday->end_date);

        while ($start <= $end) {
            $dates[] = $start->toDateString();
            $start->addDay();
        }
        return $dates;
    }

    private function addHolidayToAttendance(Holiday $holiday, string $date): void
    {
        Log::info("📌 Processing holiday attendance for '{$holiday->name}' on date: {$date}");

        $employees = Employee::where('full_time', true)
            ->where('vacation_pay', true)
            ->get();

        Log::info("👥 Found " . $employees->count() . " eligible employees for holiday pay on {$date}");

        foreach ($employees as $employee) {
            Log::info("🛠 Processing Employee ID: {$employee->id} for Holiday '{$holiday->name}' on {$date}");

            $attendanceGroup = DB::table('attendance_time_groups')
                ->where('employee_id', $employee->id)
                ->where('shift_date', $date)
                ->first();

            $externalGroupId = $attendanceGroup->external_group_id ?? null;
            $shiftStart = $attendanceGroup->shift_window_start ?? "$date 00:00:00";
            $shiftEnd = $attendanceGroup->shift_window_end ?? "$date 08:00:00";

            $exists = Attendance::where('employee_id', $employee->id)
                ->where('holiday_id', $holiday->id)
                ->where('shift_date', $date)
                ->exists();

            if (!$exists) {
                Log::info("🆕 Creating Holiday Attendance Records for Employee ID: {$employee->id} on {$date}");

                $startRecord = Attendance::create([
                    'employee_id' => $employee->id,
                    'holiday_id' => $holiday->id,
                    'punch_time' => $shiftStart,
                    'punch_type_id' => $this->getPunchTypeId('Clock In'),
                    'is_manual' => true,
                    'classification_id' => $this->getClassificationId('Holiday'),
                    'status' => 'Complete',
                    'issue_notes' => "Generated from Holiday Processing - Start ({$holiday->name})",
                    'external_group_id' => $externalGroupId,
                    'shift_date' => $date,
                ]);

                $endRecord = Attendance::create([
                    'employee_id' => $employee->id,
                    'holiday_id' => $holiday->id,
                    'punch_time' => $shiftEnd,
                    'punch_type_id' => $this->getPunchTypeId('Clock Out'),
                    'is_manual' => true,
                    'classification_id' => $this->getClassificationId('Holiday'),
                    'status' => 'Complete',
                    'issue_notes' => "Generated from Holiday Processing - End ({$holiday->name})",
                    'external_group_id' => $externalGroupId,
                    'shift_date' => $date,
                ]);

                Log::info("✅ Successfully inserted Holiday Attendance - Start Record ID: " . ($startRecord->id ?? 'NULL'));
                Log::info("✅ Successfully inserted Holiday Attendance - End Record ID: " . ($endRecord->id ?? 'NULL'));
            } else {
                Log::info("⏩ Skipping holiday attendance for Employee ID: {$employee->id}, already exists.");
            }
        }
    }

    private function getClassificationId(string $classification): ?int
    {
        return DB::table('classifications')->where('name', $classification)->value('id');
    }

    private function getPunchTypeId(string $type): ?int
    {
        return DB::table('punch_types')->where('name', $type)->value('id');
    }
}
