<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayPeriod;
use Carbon\Carbon;

/**
 * Aggregates overtime calculation results for a pay period.
 */
class OvertimeResult
{
    protected Employee $employee;

    protected PayPeriod $payPeriod;

    /**
     * @var array<string, DayOvertimeResult> Daily results indexed by date string
     */
    protected array $dailyResults = [];

    /**
     * @var array<string, array> Weekly aggregations indexed by week start date
     */
    protected array $weeklyAggregations = [];

    protected bool $isExempt = false;

    protected ?string $exemptReason = null;

    public function __construct(Employee $employee, PayPeriod $payPeriod)
    {
        $this->employee = $employee;
        $this->payPeriod = $payPeriod;
    }

    /**
     * Mark this result as exempt from overtime.
     */
    public function markExempt(string $reason): self
    {
        $this->isExempt = true;
        $this->exemptReason = $reason;

        return $this;
    }

    /**
     * Check if the employee is exempt from overtime.
     */
    public function isExempt(): bool
    {
        return $this->isExempt;
    }

    /**
     * Get the exemption reason.
     */
    public function getExemptReason(): ?string
    {
        return $this->exemptReason;
    }

    /**
     * Add a daily result.
     */
    public function addDayResult(DayOvertimeResult $result): self
    {
        $this->dailyResults[$result->date->toDateString()] = $result;

        return $this;
    }

    /**
     * Get a daily result by date.
     */
    public function getDayResult(Carbon|string $date): ?DayOvertimeResult
    {
        if ($date instanceof Carbon) {
            $date = $date->toDateString();
        }

        return $this->dailyResults[$date] ?? null;
    }

    /**
     * Get all daily results.
     *
     * @return array<string, DayOvertimeResult>
     */
    public function getDailyResults(): array
    {
        return $this->dailyResults;
    }

    /**
     * Get the total regular hours.
     */
    public function getTotalRegularHours(): float
    {
        return array_reduce($this->dailyResults, function ($carry, DayOvertimeResult $result) {
            return $carry + $result->regularHours;
        }, 0.0);
    }

    /**
     * Get the total overtime hours.
     */
    public function getTotalOvertimeHours(): float
    {
        return array_reduce($this->dailyResults, function ($carry, DayOvertimeResult $result) {
            return $carry + $result->overtimeHours;
        }, 0.0);
    }

    /**
     * Get the total double-time hours.
     */
    public function getTotalDoubleTimeHours(): float
    {
        return array_reduce($this->dailyResults, function ($carry, DayOvertimeResult $result) {
            return $carry + $result->doubleTimeHours;
        }, 0.0);
    }

    /**
     * Get the total holiday hours.
     */
    public function getTotalHolidayHours(): float
    {
        return array_reduce($this->dailyResults, function ($carry, DayOvertimeResult $result) {
            return $carry + $result->holidayHours;
        }, 0.0);
    }

    /**
     * Get the grand total of all hours worked.
     */
    public function getTotalHoursWorked(): float
    {
        return array_reduce($this->dailyResults, function ($carry, DayOvertimeResult $result) {
            return $carry + $result->totalHours;
        }, 0.0);
    }

    /**
     * Convert regular hours to overtime based on weekly threshold.
     * This is called after all daily calculations are done.
     *
     * @param  float  $weeklyThreshold  Hours threshold (e.g., 40)
     * @param  float  $multiplier  Overtime multiplier (e.g., 1.5)
     */
    public function applyWeeklyThreshold(float $weeklyThreshold, float $multiplier = 1.5): self
    {
        // Group daily results by week (Sunday to Saturday)
        $weeklyGroups = $this->groupByWeek();

        foreach ($weeklyGroups as $weekStart => $days) {
            // Calculate total regular hours for the week
            $weeklyRegular = array_reduce($days, function ($carry, DayOvertimeResult $result) {
                return $carry + $result->regularHours;
            }, 0.0);

            // If weekly regular hours exceed threshold, convert excess to OT
            if ($weeklyRegular > $weeklyThreshold) {
                $excessHours = $weeklyRegular - $weeklyThreshold;
                $this->convertRegularToOvertime($days, $excessHours);
            }

            // Store weekly aggregation for reference
            $this->weeklyAggregations[$weekStart] = [
                'total_hours' => array_reduce($days, fn ($c, $d) => $c + $d->totalHours, 0.0),
                'regular_hours' => array_reduce($days, fn ($c, $d) => $c + $d->regularHours, 0.0),
                'overtime_hours' => array_reduce($days, fn ($c, $d) => $c + $d->overtimeHours, 0.0),
                'double_time_hours' => array_reduce($days, fn ($c, $d) => $c + $d->doubleTimeHours, 0.0),
                'holiday_hours' => array_reduce($days, fn ($c, $d) => $c + $d->holidayHours, 0.0),
                'threshold' => $weeklyThreshold,
            ];
        }

        return $this;
    }

    /**
     * Group daily results by week (Sunday to Saturday).
     *
     * @return array<string, DayOvertimeResult[]>
     */
    protected function groupByWeek(): array
    {
        $weeks = [];

        foreach ($this->dailyResults as $result) {
            $weekStart = $result->date->copy()->startOfWeek(Carbon::SUNDAY)->toDateString();

            if (! isset($weeks[$weekStart])) {
                $weeks[$weekStart] = [];
            }

            $weeks[$weekStart][] = $result;
        }

        // Sort each week's days chronologically
        foreach ($weeks as $weekStart => $days) {
            usort($weeks[$weekStart], function (DayOvertimeResult $a, DayOvertimeResult $b) {
                return $a->date->timestamp <=> $b->date->timestamp;
            });
        }

        return $weeks;
    }

    /**
     * Convert regular hours to overtime, starting from the last day of the week.
     *
     * @param  DayOvertimeResult[]  $days  Days to convert (should be sorted by date)
     * @param  float  $hoursToConvert  Total hours to convert from regular to OT
     */
    protected function convertRegularToOvertime(array $days, float $hoursToConvert): void
    {
        // Work backwards from the last day (most recent hours become OT first)
        $reversedDays = array_reverse($days);

        $remaining = $hoursToConvert;

        foreach ($reversedDays as $day) {
            if ($remaining <= 0) {
                break;
            }

            // Only convert regular hours (skip days that are already all special hours)
            if ($day->regularHours > 0) {
                $toConvert = min($day->regularHours, $remaining);
                $day->regularHours -= $toConvert;
                $day->overtimeHours += $toConvert;
                $remaining -= $toConvert;

                // Update the reason to reflect weekly threshold
                if ($day->reason && ! str_contains($day->reason, 'Weekly threshold')) {
                    $day->reason .= ' + Weekly threshold OT';
                } else {
                    $day->reason = 'Weekly threshold OT';
                }
            }
        }
    }

    /**
     * Get weekly aggregations.
     *
     * @return array<string, array>
     */
    public function getWeeklyAggregations(): array
    {
        if (empty($this->weeklyAggregations)) {
            // Generate aggregations if not yet done
            $weeklyGroups = $this->groupByWeek();
            foreach ($weeklyGroups as $weekStart => $days) {
                $this->weeklyAggregations[$weekStart] = [
                    'total_hours' => array_reduce($days, fn ($c, $d) => $c + $d->totalHours, 0.0),
                    'regular_hours' => array_reduce($days, fn ($c, $d) => $c + $d->regularHours, 0.0),
                    'overtime_hours' => array_reduce($days, fn ($c, $d) => $c + $d->overtimeHours, 0.0),
                    'double_time_hours' => array_reduce($days, fn ($c, $d) => $c + $d->doubleTimeHours, 0.0),
                    'holiday_hours' => array_reduce($days, fn ($c, $d) => $c + $d->holidayHours, 0.0),
                ];
            }
        }

        return $this->weeklyAggregations;
    }

    /**
     * Get a summary of the pay period.
     */
    public function getSummary(): array
    {
        return [
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->full_name,
            'pay_period_id' => $this->payPeriod->id,
            'is_exempt' => $this->isExempt,
            'exempt_reason' => $this->exemptReason,
            'total_hours_worked' => round($this->getTotalHoursWorked(), 2),
            'regular_hours' => round($this->getTotalRegularHours(), 2),
            'overtime_hours' => round($this->getTotalOvertimeHours(), 2),
            'double_time_hours' => round($this->getTotalDoubleTimeHours(), 2),
            'holiday_hours' => round($this->getTotalHolidayHours(), 2),
            'days_worked' => count($this->dailyResults),
            'weekly_breakdown' => $this->getWeeklyAggregations(),
        ];
    }

    /**
     * Get the breakdown suitable for payroll export.
     */
    public function getExportBreakdown(): array
    {
        return [
            'regular' => round($this->getTotalRegularHours(), 2),
            'overtime' => round($this->getTotalOvertimeHours(), 2),
            'double_time' => round($this->getTotalDoubleTimeHours(), 2),
            'holiday' => round($this->getTotalHolidayHours(), 2),
            'total' => round($this->getTotalHoursWorked(), 2),
            'weeks' => $this->getWeeklyAggregations(),
        ];
    }

    /**
     * Convert all results to an array for logging.
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employee->id,
            'pay_period_id' => $this->payPeriod->id,
            'summary' => $this->getSummary(),
            'daily_breakdown' => array_map(
                fn (DayOvertimeResult $r) => $r->toArray(),
                $this->dailyResults
            ),
        ];
    }

    /**
     * Get the employee.
     */
    public function getEmployee(): Employee
    {
        return $this->employee;
    }

    /**
     * Get the pay period.
     */
    public function getPayPeriod(): PayPeriod
    {
        return $this->payPeriod;
    }
}
