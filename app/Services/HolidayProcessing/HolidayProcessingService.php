<?php

namespace App\Services\HolidayProcessing;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Employee;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HolidayProcessingService
{
    /**
     * ✅ New Method: Process Holidays Only for the Given Pay Period
     */
    public function processHolidaysForPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("🔍 [HolidayProcessingService] Processing Holidays for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        $holidays = Holiday::whereBetween('start_date', [$startDate, $endDate])
            ->orWhere(function ($query) use ($startDate, $endDate) {
                $query->where('is_recurring', true)
                    ->whereBetween('start_date', [$startDate, $endDate]);
            })
            ->get();

        Log::info("📆 Found " . $holidays->count() . " holidays within this pay period.");

        foreach ($holidays as $holiday) {
            $this->processHolidayForEmployees($holiday);
        }

        Log::info("✅ [HolidayProcessingService] Completed processing Holidays for PayPeriod ID: {$payPeriod->id}.");
    }

    /**
     * ✅ Handles Processing of Holiday Attendance for Eligible Employees
     */
    public function processHolidayForEmployees(Holiday $holiday): void
    {
        Log::info("🔍 [HolidayProcessingService] Processing Holiday: {$holiday->name}");

        $eligibleEmployees = Employee::where('full_time', true)
            ->where('vacation_pay', true)
            ->get();

        Log::info("👥 Found " . $eligibleEmployees->count() . " eligible employees for holiday pay.");

        foreach ($eligibleEmployees as $employee) {
            $this->processEmployeeHolidayAttendance($employee, $holiday);
        }

        Log::info("✅ [HolidayProcessingService] Completed processing for Holiday: {$holiday->name}.");
    }

    /**
     * ✅ Handles per-employee holiday attendance.
     */
    private function processEmployeeHolidayAttendance(Employee $employee, Holiday $holiday): void
    {
        Log::info("🛠 Processing Employee ID: {$employee->id} for Holiday '{$holiday->name}'");

        $holidayDates = $this->generateHolidayDates($holiday);

        foreach ($holidayDates as $date) {
            $attendanceGroup = DB::table('attendance_time_groups')
                ->where('employee_id', $employee->id)
                ->where('shift_date', $date)
                ->first();

            $externalGroupId = $attendanceGroup->external_group_id ?? null;
            $shiftStart = $attendanceGroup->shift_window_start ?? "$date 08:00:00";
            $shiftEnd = $attendanceGroup->shift_window_end ?? "$date 16:00:00";

            $exists = Attendance::where('employee_id', $employee->id)
                ->where('holiday_id', $holiday->id)
                ->where('shift_date', $date)
                ->exists();

            if (!$exists) {
                $this->createHolidayAttendanceRecords($employee, $holiday, $date, $externalGroupId, $shiftStart, $shiftEnd);
            } else {
                Log::info("⏩ Skipping existing holiday attendance for Employee ID: {$employee->id} on {$date}");
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

    private function createHolidayAttendanceRecords(Employee $employee, Holiday $holiday, string $date, ?string $externalGroupId, string $shiftStart, string $shiftEnd): void
    {
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

        Log::info("✅ Successfully inserted Holiday Attendance - Start ID: " . ($startRecord->id ?? 'NULL'));
        Log::info("✅ Successfully inserted Holiday Attendance - End ID: " . ($endRecord->id ?? 'NULL'));
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
