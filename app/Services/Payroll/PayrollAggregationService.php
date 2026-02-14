<?php

namespace App\Services\Payroll;

use App\Models\Attendance;
use App\Models\Classification;
use App\Models\Employee;
use App\Models\IntegrationConnection;
use App\Models\PayPeriod;
use App\Models\PayPeriodEmployeeSummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PayrollAggregationService
{
    protected OvertimeCalculationService $overtimeService;

    public function __construct(OvertimeCalculationService $overtimeService)
    {
        $this->overtimeService = $overtimeService;
    }

    /**
     * Aggregate time data for all employees in a pay period
     */
    public function aggregatePayPeriod(PayPeriod $payPeriod): Collection
    {
        Log::info("[PayrollAggregation] Starting aggregation for PayPeriod {$payPeriod->id}");

        // Get all employees with attendance in this pay period
        $employeeIds = $this->getEmployeesWithAttendance($payPeriod);

        $results = collect();

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);
            if (! $employee) {
                continue;
            }

            $summary = $this->aggregateEmployeeHours($employee, $payPeriod);
            $results->push($summary);
        }

        Log::info("[PayrollAggregation] Completed aggregation for {$results->count()} employees");

        return $results;
    }

    /**
     * Aggregate hours for a single employee in a pay period
     */
    public function aggregateEmployeeHours(Employee $employee, PayPeriod $payPeriod): array
    {
        // Get all attendance records for this employee in the pay period
        $attendanceRecords = $this->getEmployeeAttendance($employee, $payPeriod);

        // Calculate worked hours from punch pairs
        $workedHoursByDay = $this->calculateWorkedHoursByDay($attendanceRecords);

        // Get classification-based hours (vacation, holiday, etc.)
        $classificationHours = $this->getClassificationHours($employee, $payPeriod);

        // Calculate weekly totals and apply overtime rules
        $weeklyBreakdown = $this->calculateWeeklyBreakdown($employee, $payPeriod, $workedHoursByDay);

        // Build summary
        // Holiday hours from overtime calculation (worked on holiday) + classification hours (holiday pay when not worked)
        $calculatedHolidayHours = $weeklyBreakdown['holiday'] ?? 0;
        $classificationHolidayHours = $classificationHours['HOLIDAY'] ?? 0;

        $summary = [
            'employee_id' => $employee->id,
            'employee_external_id' => $employee->external_id,
            'employee_name' => $employee->full_name,
            'pay_period_id' => $payPeriod->id,
            'regular_hours' => $weeklyBreakdown['regular'],
            'overtime_hours' => $weeklyBreakdown['overtime'],
            'double_time_hours' => $weeklyBreakdown['double_time'],
            'vacation_hours' => $classificationHours['VACATION'] ?? 0,
            'holiday_hours' => $calculatedHolidayHours + $classificationHolidayHours,
            'sick_hours' => $classificationHours['SICK'] ?? 0,
            'pto_hours' => $classificationHours['PTO'] ?? 0,
            'other_hours' => $classificationHours['OTHER'] ?? 0,
            'total_hours' => $weeklyBreakdown['total'] + array_sum($classificationHours),
            'daily_breakdown' => $workedHoursByDay,
            'weekly_breakdown' => $weeklyBreakdown['weeks'],
        ];

        // Store in pay_period_employee_summaries table
        $this->storeSummary($payPeriod, $employee, $summary);

        return $summary;
    }

    /**
     * Get employees with attendance records in the pay period
     */
    protected function getEmployeesWithAttendance(PayPeriod $payPeriod): array
    {
        return Attendance::whereBetween('punch_time', [
            $payPeriod->start_date->startOfDay(),
            $payPeriod->end_date->endOfDay(),
        ])
            ->whereNotNull('employee_id')
            ->whereIn('status', ['Complete', 'Migrated', 'Posted'])
            ->distinct()
            ->pluck('employee_id')
            ->toArray();
    }

    /**
     * Get attendance records for an employee in a pay period
     */
    protected function getEmployeeAttendance(Employee $employee, PayPeriod $payPeriod): Collection
    {
        return Attendance::where('employee_id', $employee->id)
            ->whereBetween('punch_time', [
                $payPeriod->start_date->startOfDay(),
                $payPeriod->end_date->endOfDay(),
            ])
            ->whereIn('status', ['Complete', 'Migrated', 'Posted'])
            ->orderBy('punch_time')
            ->get();
    }

    /**
     * Calculate worked hours by day from attendance records
     */
    protected function calculateWorkedHoursByDay(Collection $attendanceRecords): array
    {
        // Group attendance by shift_date or punch_time date
        $byDay = $attendanceRecords->groupBy(function ($record) {
            return $record->shift_date ?? Carbon::parse($record->punch_time)->toDateString();
        });

        $dailyHours = [];

        foreach ($byDay as $date => $records) {
            // Group by external_group_id to handle multiple shifts per day
            $byGroup = $records->groupBy('external_group_id');

            $dayTotal = 0.0;

            foreach ($byGroup as $groupId => $groupRecords) {
                // Calculate hours from punch pairs
                $groupHours = $this->calculateGroupHours($groupRecords);
                $dayTotal += $groupHours;
            }

            $dailyHours[$date] = round($dayTotal, 2);
        }

        return $dailyHours;
    }

    /**
     * Calculate hours from a group of attendance records (punch pairs)
     */
    protected function calculateGroupHours(Collection $records): float
    {
        // Sort by punch time
        $sorted = $records->sortBy('punch_time')->values();

        if ($sorted->count() < 2) {
            return 0.0;
        }

        $totalMinutes = 0;
        $punchIn = null;

        foreach ($sorted as $record) {
            $punchType = $record->punchType;
            $typeName = $punchType ? strtolower($punchType->name) : '';

            // Determine if this is a clock-in or clock-out based on punch_type or punch_state
            // AUDIT: 2026-02-13 - Fixed bug: punch_state enum only allows 'start', 'stop', 'unknown' (not 'in'/'out')
            $isClockIn = str_contains($typeName, 'in') ||
                str_contains($typeName, 'start') ||
                $record->punch_state === 'start';

            $isClockOut = str_contains($typeName, 'out') ||
                str_contains($typeName, 'end') ||
                $record->punch_state === 'stop';

            if ($isClockIn && ! $punchIn) {
                $punchIn = Carbon::parse($record->punch_time);
            } elseif ($isClockOut && $punchIn) {
                $punchOut = Carbon::parse($record->punch_time);
                $totalMinutes += $punchIn->diffInMinutes($punchOut);
                $punchIn = null;
            }
        }

        return round($totalMinutes / 60, 2);
    }

    /**
     * Get hours by classification (vacation, holiday, etc.)
     */
    protected function getClassificationHours(Employee $employee, PayPeriod $payPeriod): array
    {
        // Get attendance records with classifications
        $classified = Attendance::where('employee_id', $employee->id)
            ->whereBetween('punch_time', [
                $payPeriod->start_date->startOfDay(),
                $payPeriod->end_date->endOfDay(),
            ])
            ->whereNotNull('classification_id')
            ->with('classification')
            ->get();

        $hours = [];

        foreach ($classified as $record) {
            $code = $record->classification->code ?? 'OTHER';
            // Assume 8 hours for full-day classifications like vacation/holiday
            // TODO: This should come from actual hours field if available
            $recordHours = 8.0;

            if (! isset($hours[$code])) {
                $hours[$code] = 0.0;
            }
            $hours[$code] += $recordHours;
        }

        return $hours;
    }

    /**
     * Calculate weekly breakdown with overtime using the enhanced overtime engine.
     */
    protected function calculateWeeklyBreakdown(
        Employee $employee,
        PayPeriod $payPeriod,
        array $dailyHours
    ): array {
        // Use the new pay period overtime calculation
        $overtimeResult = $this->overtimeService->calculatePayPeriodOvertime(
            $employee,
            $payPeriod,
            $dailyHours
        );

        // Get the export breakdown which includes weekly aggregations
        $exportBreakdown = $overtimeResult->getExportBreakdown();

        // Format weekly breakdown to match expected structure
        $weeks = [];
        $weekNum = 1;
        foreach ($exportBreakdown['weeks'] as $weekStart => $weekData) {
            $weeks["week_{$weekNum}"] = [
                'start' => $weekStart,
                'end' => Carbon::parse($weekStart)->endOfWeek(Carbon::SATURDAY)->toDateString(),
                'total_hours' => $weekData['total_hours'] ?? 0,
                'regular' => $weekData['regular_hours'] ?? 0,
                'overtime' => $weekData['overtime_hours'] ?? 0,
                'double_time' => $weekData['double_time_hours'] ?? 0,
                'holiday' => $weekData['holiday_hours'] ?? 0,
            ];
            $weekNum++;
        }

        return [
            'regular' => $exportBreakdown['regular'],
            'overtime' => $exportBreakdown['overtime'],
            'double_time' => $exportBreakdown['double_time'],
            'holiday' => $exportBreakdown['holiday'] ?? 0,
            'total' => $exportBreakdown['total'],
            'weeks' => $weeks,
        ];
    }

    /**
     * Store summary in the database
     */
    protected function storeSummary(PayPeriod $payPeriod, Employee $employee, array $summary): void
    {
        // Get or create classification IDs
        $classifications = [
            'REGULAR' => $summary['regular_hours'],
            'OVERTIME' => $summary['overtime_hours'],
            'DOUBLETIME' => $summary['double_time_hours'],
            'VACATION' => $summary['vacation_hours'],
            'HOLIDAY' => $summary['holiday_hours'],
            'SICK' => $summary['sick_hours'],
            'PTO' => $summary['pto_hours'],
        ];

        foreach ($classifications as $code => $hours) {
            if ($hours <= 0) {
                continue;
            }

            $classification = Classification::firstOrCreate(
                ['code' => $code],
                ['name' => ucfirst(strtolower($code)), 'description' => "{$code} hours"]
            );

            PayPeriodEmployeeSummary::updateOrCreate(
                [
                    'pay_period_id' => $payPeriod->id,
                    'employee_id' => $employee->id,
                    'classification_id' => $classification->id,
                ],
                [
                    'hours' => $hours,
                    'is_finalized' => false,
                ]
            );
        }
    }

    /**
     * Get aggregated data for export by payroll provider
     */
    public function getDataForProvider(PayPeriod $payPeriod, IntegrationConnection $provider): Collection
    {
        return PayPeriodEmployeeSummary::where('pay_period_id', $payPeriod->id)
            ->whereHas('employee', function ($query) use ($provider) {
                $query->where('payroll_provider_id', $provider->id);
            })
            ->with(['employee', 'classification'])
            ->get()
            ->groupBy('employee_id')
            ->map(function ($summaries, $employeeId) {
                $employee = $summaries->first()->employee;
                $data = [
                    'employee_id' => $employeeId,
                    'employee_external_id' => $employee->external_id,
                    'employee_name' => $employee->full_name,
                    'department' => $employee->department?->name,
                ];

                foreach ($summaries as $summary) {
                    $code = strtolower($summary->classification->code);
                    $data["{$code}_hours"] = $summary->hours;
                }

                return $data;
            })
            ->values();
    }

    /**
     * Finalize summaries for a pay period
     *
     * @return int Number of records finalized
     */
    public function finalizeSummaries(PayPeriod $payPeriod): int
    {
        return PayPeriodEmployeeSummary::where('pay_period_id', $payPeriod->id)
            ->where('is_finalized', false)
            ->update(['is_finalized' => true]);
    }
}
