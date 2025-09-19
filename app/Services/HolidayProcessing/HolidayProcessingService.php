<?php

namespace App\Services\HolidayProcessing;

use App\Models\Attendance;
use App\Models\VacationCalendar;
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

        // Note: Holiday processing is now handled by the HolidayTemplate system
        // This service is temporarily disabled pending integration with the new system
        Log::info("[Holiday] Holiday processing temporarily disabled - holidays are managed via HolidayTemplate system");
        return;

        Log::info("[Holiday] Completed processing Holidays for PayPeriod ID: {$payPeriod->id}.");
    }

    public function processHolidayForEmployees(string $date, VacationCalendar $holidayExample): void
    {
        Log::info("[Holiday] Processing Holiday on date: {$date}");

        $eligibleEmployees = Employee::where('full_time', true)
            ->where('vacation_pay', true)
            ->get();

        Log::info("[Holiday] Found " . $eligibleEmployees->count() . " eligible employees.");

        foreach ($eligibleEmployees as $employee) {
            $this->processEmployeeHolidayAttendance($employee, $date, $holidayExample);
        }

        Log::info("[Holiday] Completed processing for Holiday on: {$date}.");
    }

    private function processEmployeeHolidayAttendance(Employee $employee, string $date, VacationCalendar $holidayExample): void
    {
        Log::info("[Holiday] Processing Employee ID: {$employee->id} for Holiday on '{$date}'");

        $attendanceGroup = DB::table('attendance_time_groups')
            ->where('employee_id', $employee->id)
            ->where('shift_date', $date)
            ->first();

        $externalGroupId = $attendanceGroup->external_group_id ?? null;
        $shiftStart = $attendanceGroup->shift_window_start ?? "$date 08:00:00";
        $shiftEnd = $attendanceGroup->shift_window_end ?? "$date 16:00:00";

        $exists = Attendance::where('employee_id', $employee->id)
            ->where('shift_date', $date)
            ->where('classification_id', $this->getClassificationId('Holiday'))
            ->exists();

        if (!$exists) {
            $this->createHolidayAttendanceRecords($employee, $date, $externalGroupId, $shiftStart, $shiftEnd, $holidayExample);
        } else {
            Log::info("[Holiday] Skipping existing holiday attendance for Employee ID: {$employee->id} on {$date}");
        }
    }

    private function createHolidayAttendanceRecords(Employee $employee, string $date, ?string $externalGroupId, string $shiftStart, string $shiftEnd, VacationCalendar $holidayExample): void
    {
        Log::info("[Holiday] Creating Attendance Records for Employee ID: {$employee->id} on {$date}");

        $holidayClassificationId = $this->getClassificationId('Holiday');

        $startRecord = Attendance::create([
            'employee_id' => $employee->id,
            'punch_time' => $shiftStart,
            'punch_type_id' => $this->getPunchTypeId('Clock In'),
            'punch_state' => 'start',
            'is_manual' => true,
            'classification_id' => $holidayClassificationId,
            'status' => 'Complete',
            'issue_notes' => "Generated from Holiday Processing - Start (Holiday on {$date})",
            'external_group_id' => $externalGroupId,
            'shift_date' => $date,
        ]);

        $endRecord = Attendance::create([
            'employee_id' => $employee->id,
            'punch_time' => $shiftEnd,
            'punch_type_id' => $this->getPunchTypeId('Clock Out'),
            'punch_state' => 'stop',
            'is_manual' => true,
            'classification_id' => $holidayClassificationId,
            'status' => 'Complete',
            'issue_notes' => "Generated from Holiday Processing - End (Holiday on {$date})",
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
