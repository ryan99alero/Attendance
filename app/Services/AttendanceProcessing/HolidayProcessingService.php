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
    public function processHolidays(string $startDate, string $endDate): void
    {
        // Ensure there are no attendance issues in the pay periods
        $payPeriods = PayPeriod::whereBetween('start_date', [$startDate, $endDate])->get();

        foreach ($payPeriods as $payPeriod) {
            if ($payPeriod->attendanceIssuesCount() > 0) {
                Log::warning("PayPeriod ID {$payPeriod->id} has unresolved attendance issues. Holidays will not be processed.");
                continue;
            }
        }

        // Fetch holidays within the given date range
        $holidays = Holiday::whereBetween('start_date', [$startDate, $endDate])
            ->orWhere('is_recurring', true) // Include recurring holidays
            ->get();

        foreach ($holidays as $holiday) {
            $dates = $this->generateHolidayDates($holiday);

            foreach ($dates as $date) {
                $this->addHolidayToAttendance($date);
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

    private function addHolidayToAttendance(string $date): void
    {
        $employees = Employee::where('is_active', true)->get();

        foreach ($employees as $employee) {
            $exists = Attendance::where('employee_id', $employee->id)
                ->whereDate('punch_time', $date)
                ->where('classification_id', $this->getClassificationId('Holiday'))
                ->exists();

            if (!$exists) {
                Attendance::create([
                    'employee_id' => $employee->id,
                    'punch_time' => $date . ' 00:00:00',
                    'punch_type_id' => $this->getPunchTypeId('Holiday'),
                    'is_manual' => true,
                    'classification_id' => $this->getClassificationId('Holiday'),
                    'status' => 'Incomplete',
                    'issue_notes' => 'Generated from Holiday Processing',
                ]);

                Log::info("Added holiday record for Employee ID: {$employee->id} on {$date}");
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
