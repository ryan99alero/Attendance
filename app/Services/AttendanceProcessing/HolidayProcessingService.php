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

        // Ensure pay periods are valid before processing holidays
        $payPeriods = PayPeriod::whereBetween('start_date', [$startDate, $endDate])->get();
        foreach ($payPeriods as $payPeriod) {
            if ($payPeriod->attendanceIssuesCount() > 0) {
                Log::warning("⚠️ PayPeriod ID {$payPeriod->id} has unresolved attendance issues. Holidays will not be processed.");
                continue;
            }
        }

        // Fetch holidays in range, including recurring holidays
        $holidays = Holiday::whereBetween('start_date', [$startDate, $endDate])
            ->orWhere(function ($query) use ($startDate, $endDate) {
                $query->where('is_recurring', true)
                    ->whereBetween('start_date', [$startDate, $endDate]); // Ensure recurring holidays are included
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

        // Fetch employees eligible for holiday pay
        $employees = Employee::where('full_time', true)
            ->where('vacation_pay', true)
            ->get();

        Log::info("👥 Found " . $employees->count() . " eligible employees for holiday pay on {$date}");

        foreach ($employees as $employee) {
            Log::info("🛠 Processing Employee ID: {$employee->id} for Holiday '{$holiday->name}' on {$date}");

            // Fetch employee's shift schedule
            $shiftSchedule = $this->shiftScheduleService->getShiftScheduleForEmployee($employee->id);

            if (!$shiftSchedule) {
                Log::warning("⚠️ No shift schedule found for Employee ID: {$employee->id}. Defaulting to midnight shift.");
                $shift_start = "$date 00:00:00";
                $shift_end = "$date 08:00:00"; // Default to 8-hour workday
            } else {
                $shift_start = "$date " . Carbon::parse($shiftSchedule->start_time)->format('H:i:s');
                $shift_end = "$date " . Carbon::parse($shiftSchedule->end_time)->format('H:i:s');
                Log::info("✅ Employee ID: {$employee->id} - Holiday Shift: {$shift_start} to {$shift_end}");
            }

            // Check if holiday attendance already exists for this employee & holiday
            $exists = Attendance::where('employee_id', $employee->id)
                ->where('holiday_id', $holiday->id) // Ensure it's linked to the correct holiday
                ->exists();

            if (!$exists) {
                Log::info("🆕 Creating Holiday Attendance Records for Employee ID: {$employee->id} on {$date}");

                // Create Clock In Record for Holiday
                $startRecord = Attendance::create([
                    'employee_id' => $employee->id,
                    'holiday_id' => $holiday->id,  // ✅ Linking the holiday to attendance
                    'punch_time' => $shift_start,
                    'punch_type_id' => $this->getPunchTypeId('Clock In'), // ✅ Assign Clock In punch type
                    'is_manual' => true,
                    'classification_id' => $this->getClassificationId('Holiday'),
                    'status' => 'NeedsReview',
                    'issue_notes' => "Generated from Holiday Processing - Start ({$holiday->name})",
                ]);

                // Create Clock Out Record for Holiday
                $endRecord = Attendance::create([
                    'employee_id' => $employee->id,
                    'holiday_id' => $holiday->id,  // ✅ Linking the holiday to attendance
                    'punch_time' => $shift_end,
                    'punch_type_id' => $this->getPunchTypeId('Clock Out'), // ✅ Assign Clock Out punch type
                    'is_manual' => true,
                    'classification_id' => $this->getClassificationId('Holiday'),
                    'status' => 'NeedsReview',
                    'issue_notes' => "Generated from Holiday Processing - End ({$holiday->name})",
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
