<?php

namespace App\Services\VacationProcessing;

use Exception;
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
        Log::info("📅 Processing vacation records from {$startDate} to {$endDate}");

        $vacationRecords = VacationCalendar::whereBetween('vacation_date', [$startDate, $endDate])
            ->where('is_active', true)
            ->where('is_recorded', false)
            ->get();

        Log::info("✅ Found {$vacationRecords->count()} vacation records to process.");

        foreach ($vacationRecords as $record) {
            $employee = Employee::find($record->employee_id);

            if (!$employee) {
                Log::warning("⚠️ Employee not found for Vacation Record ID: {$record->id}");
                continue;
            }

            $shiftSchedule = $this->getShiftScheduleForEmployee($employee);

            if (!$shiftSchedule) {
                Log::warning("⚠️ No shift schedule found for Employee ID: {$employee->id}. Skipping...");
                continue;
            }

            try {
                // Create Clock In record
                $clockInTime = $this->getVacationPunchTime($record, $shiftSchedule);
                $this->createAttendanceRecord($employee->id, $clockInTime, 'Clock In', "Generated from Vacation Calendar (Clock In)");

                // Determine Clock Out time
                $dailyHours = $record->is_half_day ? $shiftSchedule->daily_hours / 2 : $shiftSchedule->daily_hours;
                $clockOutTime = Carbon::parse($clockInTime)->addHours($dailyHours)->format('Y-m-d H:i:s');

                // Create Clock Out record
                $this->createAttendanceRecord($employee->id, $clockOutTime, 'Clock Out', "Generated from Vacation Calendar (Clock Out)");

                // Mark vacation record as recorded only after both records are created
                $record->update(['is_recorded' => true]);

                Log::info("✅ Processed Vacation Record ID: {$record->id} for Employee ID: {$employee->id}");

            } catch (Exception $e) {
                Log::error("❌ Failed to process Vacation Record ID: {$record->id} for Employee ID: {$employee->id}. Error: {$e->getMessage()}");
            }
        }
    }

    private function createAttendanceRecord(int $employeeId, string $punchTime, string $punchType, string $issueNotes): void
    {
        $punchTypeId = $this->getPunchTypeId($punchType);

        if (!$punchTypeId) {
            Log::warning("⚠️ Invalid Punch Type: {$punchType} for Employee ID: {$employeeId}");
            return;
        }

        Attendance::create([
            'employee_id' => $employeeId,
            'punch_time' => $punchTime,
            'punch_type_id' => $punchTypeId,
            'is_manual' => true,
            'status' => 'Complete',
            'issue_notes' => $issueNotes,
        ]);

        Log::info("📝 Created {$punchType} attendance record for Employee ID: {$employeeId}, Time: {$punchTime}");
    }

    private function getPunchTypeId(string $type): ?int
    {
        return DB::table('punch_types')->where('name', $type)->value('id');
    }

    private function getShiftScheduleForEmployee(Employee $employee): ?ShiftSchedule
    {
        return $employee->shift_schedule_id
            ? $employee->shiftSchedule
            : ShiftSchedule::where('department_id', $employee->department_id)->first();
    }

    private function getVacationPunchTime(VacationCalendar $vacation, ShiftSchedule $shiftSchedule): string
    {
        try {
            $validatedDate = Carbon::parse($vacation->vacation_date)->format('Y-m-d');
            $validatedTime = Carbon::parse($shiftSchedule->start_time)->format('H:i:s');

            $punchTime = "{$validatedDate} {$validatedTime}";
            Log::info("🕒 Validated punch time for Vacation ID {$vacation->id}: {$punchTime}");

            return $punchTime;
        } catch (Exception $e) {
            Log::error("❌ Failed to create punch_time for Vacation ID: {$vacation->id}. Error: {$e->getMessage()}");
            return now()->toDateTimeString(); // Fallback in case of error
        }
    }
}
