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
    public function processHolidaysForPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("[Holiday] Processing Holidays for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();

        $holidays = Holiday::whereBetween('start_date', [$startDate, $endDate])
            ->orWhere(function ($query) use ($startDate, $endDate) {
                $query->where('is_recurring', true)
                    ->whereBetween('start_date', [$startDate, $endDate]);
            })
            ->get();

        Log::info("[Holiday] Found " . $holidays->count() . " holidays within this pay period.");

        foreach ($holidays as $holiday) {
            $this->processHolidayForEmployees($holiday);
        }

        Log::info("[Holiday] Completed processing Holidays for PayPeriod ID: {$payPeriod->id}.");
    }

    public function processHolidayForEmployees(Holiday $holiday): void
    {
        Log::info("[Holiday] Processing Holiday: {$holiday->name}");

        $eligibleEmployees = Employee::where('full_time', true)
            ->where('vacation_pay', true)
            ->get();

        Log::info("[Holiday] Found " . $eligibleEmployees->count() . " eligible employees.");

        foreach ($eligibleEmployees as $employee) {
            $this->processEmployeeHolidayAttendance($employee, $holiday);
        }

        Log::info("[Holiday] Completed processing for Holiday: {$holiday->name}.");
    }

    private function processEmployeeHolidayAttendance(Employee $employee, Holiday $holiday): void
    {
        Log::info("[Holiday] Processing Employee ID: {$employee->id} for Holiday '{$holiday->name}'");

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
                Log::info("[Holiday] Skipping existing holiday attendance for Employee ID: {$employee->id} on {$date}");
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
        Log::info("[Holiday] Creating Attendance Records for Employee ID: {$employee->id} on {$date}");

        $startRecord = Attendance::create([
            'employee_id' => $employee->id,
            'holiday_id' => $holiday->id,
            'punch_time' => $shiftStart,
            'punch_type_id' => $this->getPunchTypeId('Clock In'),
            'punch_state' => 'start',
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
            'punch_state' => 'stop',
            'is_manual' => true,
            'classification_id' => $this->getClassificationId('Holiday'),
            'status' => 'Complete',
            'issue_notes' => "Generated from Holiday Processing - End ({$holiday->name})",
            'external_group_id' => $externalGroupId,
            'shift_date' => $date,
        ]);

        Log::info("[Holiday] Inserted Holiday Attendance - Start ID: " . ($startRecord->id ?? 'NULL'));
        Log::info("[Holiday] Inserted Holiday Attendance - End ID: " . ($endRecord->id ?? 'NULL'));
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
