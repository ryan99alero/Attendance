<?php

namespace App\Services\AttendanceProcessing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
use App\Models\VacationCalendar;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use Carbon\Carbon;

class VacationTimeProcessAttendanceService
{
    public function processVacationDays(string $startDate, string $endDate): void
    {
        $vacationRecords = VacationCalendar::whereBetween('vacation_date', [$startDate, $endDate])
            ->where('is_active', true)
            ->where('is_recorded', false)
            ->get();

        Log::info("Processing vacation records from {$startDate} to {$endDate}. Total: " . $vacationRecords->count());

        foreach ($vacationRecords as $record) {
            $employee = Employee::find($record->employee_id);

            if (!$employee) {
                Log::warning("Employee not found for Vacation Record ID: {$record->id}");
                continue;
            }

            $shiftSchedule = $this->getShiftScheduleForEmployee($employee);

            if (!$shiftSchedule) {
                Log::warning("Shift schedule not found for Employee ID: {$employee->id}");
                continue;
            }

            try {
                // Create Clock In record
                $clockInTime = $this->getVacationPunchTime($record, $shiftSchedule);
                $clockInTypeId = $this->getPunchTypeId('Clock In');
                $this->createAttendanceRecord($employee->id, $clockInTime, $clockInTypeId, 'Generated from Vacation Calendar (Clock In)');

                // Determine Clock Out time
                $dailyHours = $record->is_half_day
                    ? $shiftSchedule->daily_hours / 2
                    : $shiftSchedule->daily_hours;

                $clockOutTime = Carbon::createFromFormat('Y-m-d H:i:s', $clockInTime)
                    ->addHours($dailyHours)
                    ->format('Y-m-d H:i:s');

                // Create Clock Out record
                $clockOutTypeId = $this->getPunchTypeId('Clock Out');
                $this->createAttendanceRecord($employee->id, $clockOutTime, $clockOutTypeId, 'Generated from Vacation Calendar (Clock Out)');

                // Mark vacation record as recorded only after both records are created
                $record->is_recorded = true;
                $record->save();

                Log::info("Processed Vacation Record ID: {$record->id} for Employee ID: {$employee->id}");

            } catch (\Exception $e) {
                Log::error("Failed to process Vacation Record ID: {$record->id} for Employee ID: {$employee->id}. Error: {$e->getMessage()}");
            }
        }
    }

    private function createAttendanceRecord(int $employeeId, string $punchTime, int $punchTypeId, string $issueNotes): void
    {
        $attendance = new Attendance();
        $attendance->employee_id = $employeeId;
        $attendance->punch_time = $punchTime;
        $attendance->punch_type_id = $punchTypeId;
        $attendance->is_manual = true;
        $attendance->status = 'Complete';
        $attendance->issue_notes = $issueNotes;
        $attendance->save();

        Log::info("Created Attendance Record for Employee ID: {$employeeId}, Punch Time: {$punchTime}, Punch Type ID: {$punchTypeId}");
    }

    private function getPunchTypeId(string $type): ?int
    {
        return DB::table('punch_types')->where('name', $type)->value('id');
    }

    private function getShiftScheduleForEmployee($employee): ?ShiftSchedule
    {
        return $employee->shift_schedule_id
            ? $employee->shiftSchedule
            : ShiftSchedule::where('department_id', $employee->department_id)->first();
    }

    private function getVacationPunchTime($vacation, $shiftSchedule): string
    {
        $rawStartTime = $shiftSchedule->start_time; // e.g., '07:00:00'
        $rawVacationDate = $vacation->vacation_date; // e.g., '2024-12-24'

        try {
            $vacationDate = explode(' ', $rawVacationDate)[0];
            $startTime = explode(' ', $rawStartTime)[1] ?? $rawStartTime;

            $validatedDate = Carbon::createFromFormat('Y-m-d', $vacationDate)->format('Y-m-d');
            $validatedTime = Carbon::createFromFormat('H:i:s', $startTime)->format('H:i:s');

            $punchTime = "{$validatedDate} {$validatedTime}";

            Log::info("Validated punch time for Vacation ID {$vacation->id}: {$punchTime}");

            return Carbon::createFromFormat('Y-m-d H:i:s', $punchTime)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::error("Failed to create punch_time for Vacation ID: {$vacation->id}. Error: {$e->getMessage()}");
            return now()->toDateTimeString(); // Fallback in case of error
        }
    }
}
