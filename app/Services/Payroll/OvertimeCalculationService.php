<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\HolidayInstance;
use App\Models\OvertimeCalculationLog;
use App\Models\OvertimeRule;
use App\Models\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced overtime calculation service supporting:
 * - Consecutive day double-time with prior-day verification
 * - Shift-specific weekend/overtime day rules
 * - Holiday overtime with configurable qualification requirements
 * - Pay type filtering (Hourly, Salary, Contract)
 */
class OvertimeCalculationService
{
    protected bool $logCalculations = true;

    /**
     * Calculate overtime for an entire pay period.
     *
     * @param  Employee  $employee  The employee to calculate for
     * @param  PayPeriod  $payPeriod  The pay period
     * @param  array<string, float>  $dailyHours  Map of 'YYYY-MM-DD' => hours worked
     */
    public function calculatePayPeriodOvertime(
        Employee $employee,
        PayPeriod $payPeriod,
        array $dailyHours
    ): OvertimeResult {
        $result = new OvertimeResult($employee, $payPeriod);

        // Check if employee is overtime exempt
        if ($this->isOvertimeExempt($employee)) {
            $result->markExempt($this->getExemptReason($employee));

            // All hours are regular for exempt employees
            foreach ($dailyHours as $dateStr => $hours) {
                $date = Carbon::parse($dateStr);
                $dayResult = DayOvertimeResult::exempt($date, $hours);
                $result->addDayResult($dayResult);

                if ($this->logCalculations) {
                    $this->logDayCalculation($employee, $payPeriod, $dayResult);
                }
            }

            return $result;
        }

        // Create consecutive day tracker for the period
        $tracker = new ConsecutiveDayTracker($dailyHours);

        // Get applicable rules for this employee's shift and pay type
        $rules = $this->getApplicableRules($employee);

        // Get holidays for the pay period
        $holidays = $this->getHolidaysForPeriod($payPeriod);

        // Process each day
        foreach ($dailyHours as $dateStr => $hours) {
            if ($hours <= 0) {
                continue;
            }

            $date = Carbon::parse($dateStr);
            $dayResult = $this->calculateDayOvertime(
                $employee,
                $date,
                $hours,
                $rules,
                $holidays,
                $tracker
            );

            $result->addDayResult($dayResult);

            if ($this->logCalculations) {
                $this->logDayCalculation($employee, $payPeriod, $dayResult);
            }
        }

        // Apply weekly threshold rules after daily processing
        $this->applyWeeklyThresholds($result, $rules);

        return $result;
    }

    /**
     * Calculate overtime for a single day.
     */
    protected function calculateDayOvertime(
        Employee $employee,
        Carbon $date,
        float $hours,
        Collection $rules,
        Collection $holidays,
        ConsecutiveDayTracker $tracker
    ): DayOvertimeResult {
        $dayOfWeek = $date->dayOfWeek;

        // 1. Check for holiday first (highest priority)
        $holiday = $holidays->first(fn (HolidayInstance $h) => $h->holiday_date->isSameDay($date));

        if ($holiday && $this->employeeQualifiesForHoliday($employee, $holiday, $tracker)) {
            return DayOvertimeResult::allHoliday($date, $hours, $holiday)
                ->withContext([
                    'holiday_name' => $holiday->name,
                    'multiplier' => $holiday->holiday_multiplier,
                ]);
        }

        // 2. Check consecutive day rules (e.g., 7th day = double-time)
        $consecutiveDayRule = $this->findConsecutiveDayRule($rules, $employee, $date, $tracker);
        if ($consecutiveDayRule) {
            $consecutiveDays = $tracker->getConsecutiveDaysUpTo($date);

            return DayOvertimeResult::allDoubleTime($date, $hours, $consecutiveDayRule)
                ->withContext([
                    'consecutive_days' => $consecutiveDays,
                    'threshold' => $consecutiveDayRule->consecutive_days_threshold,
                ])
                ->withReason("{$consecutiveDays}th consecutive day - double-time");
        }

        // 3. Check weekend/specific day rules
        $weekendRule = $this->findWeekendDayRule($rules, $employee, $date, $tracker);
        if ($weekendRule) {
            // Determine if this is OT (1.5x) or DT (2.0x) based on prior-day requirement
            if ($weekendRule->requires_prior_day_worked && $weekendRule->double_time_multiplier > 1.5) {
                // This is a double-time day (e.g., Sunday after Saturday)
                return DayOvertimeResult::allDoubleTime($date, $hours, $weekendRule)
                    ->withContext([
                        'day_of_week' => $dayOfWeek,
                        'prior_day_worked' => true,
                    ])
                    ->withReason("{$date->format('l')} with prior day worked - double-time");
            } else {
                // This is an overtime day (e.g., Saturday)
                return DayOvertimeResult::allOvertime($date, $hours, $weekendRule)
                    ->withContext([
                        'day_of_week' => $dayOfWeek,
                    ])
                    ->withReason("{$date->format('l')} - overtime");
            }
        }

        // 4. Check daily threshold rules (e.g., California daily OT after 8 hours)
        $dailyThresholdRule = $this->findDailyThresholdRule($rules, $employee, $date);
        if ($dailyThresholdRule && $hours > $dailyThresholdRule->hours_threshold) {
            $result = new DayOvertimeResult($date, $hours);
            $threshold = $dailyThresholdRule->hours_threshold;

            // Split hours based on threshold
            $result->regularHours = min($hours, $threshold);
            $remainingHours = $hours - $threshold;

            // Check for double-time threshold (e.g., after 12 hours)
            $dtThreshold = $employee->double_time_threshold ?? 12.0;
            if ($hours > $dtThreshold) {
                $result->doubleTimeHours = $hours - $dtThreshold;
                $result->overtimeHours = $dtThreshold - $threshold;
            } else {
                $result->overtimeHours = $remainingHours;
            }

            return $result->withRule($dailyThresholdRule)
                ->withReason("Daily threshold ({$threshold}h) exceeded");
        }

        // 5. Default: all regular hours (weekly threshold applied later)
        return DayOvertimeResult::allRegular($date, $hours, 'Regular work day');
    }

    /**
     * Apply weekly threshold rules to convert regular hours to overtime.
     */
    protected function applyWeeklyThresholds(OvertimeResult $result, Collection $rules): void
    {
        // Find the weekly threshold rule
        $weeklyRule = $rules->first(fn (OvertimeRule $r) => $r->isWeeklyThresholdRule());

        if (! $weeklyRule) {
            // Default to 40 hours if no rule defined
            $result->applyWeeklyThreshold(40.0, 1.5);

            return;
        }

        $result->applyWeeklyThreshold(
            (float) $weeklyRule->hours_threshold,
            $weeklyRule->multiplier
        );
    }

    /**
     * Find a matching consecutive day rule.
     */
    protected function findConsecutiveDayRule(
        Collection $rules,
        Employee $employee,
        Carbon $date,
        ConsecutiveDayTracker $tracker
    ): ?OvertimeRule {
        foreach ($rules->filter(fn (OvertimeRule $r) => $r->isConsecutiveDayRule()) as $rule) {
            // Check if rule applies to this employee's pay type
            if (! $rule->appliesToPayType($employee->pay_type)) {
                continue;
            }

            // Check if consecutive days threshold is met
            $threshold = $rule->consecutive_days_threshold;
            if (! $threshold) {
                continue;
            }

            $consecutiveDays = $tracker->getConsecutiveDaysUpTo($date);

            if ($consecutiveDays >= $threshold) {
                // For rules that only apply to the final (threshold) day
                if ($rule->only_applies_to_final_day && $consecutiveDays !== $threshold) {
                    continue;
                }

                return $rule;
            }
        }

        return null;
    }

    /**
     * Find a matching weekend/specific day rule.
     */
    protected function findWeekendDayRule(
        Collection $rules,
        Employee $employee,
        Carbon $date,
        ConsecutiveDayTracker $tracker
    ): ?OvertimeRule {
        $dayOfWeek = $date->dayOfWeek;

        foreach ($rules->filter(fn (OvertimeRule $r) => $r->isWeekendDayRule()) as $rule) {
            // Check if rule applies to this day of week
            if (! $rule->appliesToDay($dayOfWeek)) {
                continue;
            }

            // Check if rule applies to this employee's pay type
            if (! $rule->appliesToPayType($employee->pay_type)) {
                continue;
            }

            // Check prior-day requirement
            if ($rule->requires_prior_day_worked && ! $tracker->priorDayWorked($date)) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    /**
     * Find a matching daily threshold rule.
     */
    protected function findDailyThresholdRule(
        Collection $rules,
        Employee $employee,
        Carbon $date
    ): ?OvertimeRule {
        $dayOfWeek = $date->dayOfWeek;

        foreach ($rules->filter(fn (OvertimeRule $r) => $r->isDailyThresholdRule()) as $rule) {
            // Check if rule applies to this day
            if (! $rule->appliesToDay($dayOfWeek)) {
                continue;
            }

            // Check if rule applies to this employee's pay type
            if (! $rule->appliesToPayType($employee->pay_type)) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    /**
     * Check if employee is overtime exempt.
     */
    protected function isOvertimeExempt(Employee $employee): bool
    {
        // Explicitly marked as exempt
        if ($employee->overtime_exempt) {
            return true;
        }

        // Salary employees are typically exempt (unless specifically included in rules)
        if ($employee->pay_type === 'salary') {
            return true;
        }

        return false;
    }

    /**
     * Get the reason for overtime exemption.
     */
    protected function getExemptReason(Employee $employee): string
    {
        if ($employee->overtime_exempt) {
            return 'Employee marked as overtime exempt';
        }

        if ($employee->pay_type === 'salary') {
            return 'Salary employee - overtime exempt';
        }

        return 'Overtime exempt';
    }

    /**
     * Check if employee qualifies for holiday pay.
     */
    protected function employeeQualifiesForHoliday(
        Employee $employee,
        HolidayInstance $holiday,
        ConsecutiveDayTracker $tracker
    ): bool {
        // Check pay type eligibility
        if (! $holiday->appliesToEmployee($employee)) {
            return false;
        }

        // Get all worked dates for checking day-before/after requirements
        $workedDates = $tracker->getWorkedDates();

        // Check day-before requirement
        if (! $holiday->dayBeforeWorked($workedDates)) {
            return false;
        }

        // Check day-after requirement
        if (! $holiday->dayAfterWorked($workedDates)) {
            return false;
        }

        return true;
    }

    /**
     * Get applicable overtime rules for an employee.
     */
    protected function getApplicableRules(Employee $employee): Collection
    {
        $shiftId = null;

        // Get shift ID from shift schedule
        if ($employee->shift_schedule_id) {
            $shiftSchedule = $employee->shiftSchedule;
            if ($shiftSchedule) {
                $shiftId = $shiftSchedule->shift_id;
            }
        }

        return OvertimeRule::active()
            ->forShift($shiftId)
            ->byPriority()
            ->get();
    }

    /**
     * Get holidays for a pay period.
     */
    protected function getHolidaysForPeriod(PayPeriod $payPeriod): Collection
    {
        return HolidayInstance::active()
            ->forDateRange($payPeriod->start_date, $payPeriod->end_date)
            ->get();
    }

    /**
     * Log a day's calculation to the audit trail.
     */
    protected function logDayCalculation(
        Employee $employee,
        PayPeriod $payPeriod,
        DayOvertimeResult $result
    ): void {
        try {
            OvertimeCalculationLog::logCalculation(
                $employee->id,
                $payPeriod->id,
                $result->date,
                [
                    'total_hours_worked' => $result->totalHours,
                    'regular_hours' => $result->regularHours,
                    'overtime_hours' => $result->overtimeHours,
                    'double_time_hours' => $result->doubleTimeHours,
                    'holiday_hours' => $result->holidayHours,
                    'overtime_rule_id' => $result->appliedRule?->id,
                    'holiday_instance_id' => $result->holidayInstance?->id,
                    'calculation_reason' => $result->reason,
                    'calculation_context' => $result->context,
                    'overtime_multiplier' => $result->getOvertimeMultiplier(),
                    'double_time_multiplier' => $result->getDoubleTimeMultiplier(),
                ]
            );
        } catch (\Exception $e) {
            Log::warning('[OvertimeCalculation] Failed to log calculation: '.$e->getMessage());
        }
    }

    /**
     * Enable or disable calculation logging.
     */
    public function setLogCalculations(bool $enabled): self
    {
        $this->logCalculations = $enabled;

        return $this;
    }

    // =====================================================
    // Legacy Methods (kept for backward compatibility)
    // =====================================================

    // AUDIT: 2026-02-13 - Deprecated methods commented out
    // These are superseded by calculatePayPeriodOvertime() which handles all overtime scenarios
    // Kept getOvertimeMultiplier() and getDoubleTimeMultiplier() as they may still be used

    /*
    **
     * Calculate overtime for an employee based on their weekly hours.
     *
     * @deprecated Use calculatePayPeriodOvertime() instead
     *
    public function calculateWeeklyOvertime(
        Employee $employee,
        float $totalHoursWorked,
        ?Carbon $weekStartDate = null
    ): array {
        // Check if employee is overtime exempt
        if ($this->isOvertimeExempt($employee)) {
            return [
                'regular' => $totalHoursWorked,
                'overtime' => 0.0,
                'double_time' => 0.0,
            ];
        }

        // Get applicable overtime rule
        $rules = $this->getApplicableRules($employee);
        $weeklyRule = $rules->first(fn (OvertimeRule $r) => $r->isWeeklyThresholdRule());

        $threshold = $weeklyRule?->hours_threshold ?? 40;
        $doubleTimeThreshold = $employee->double_time_threshold ?? null;

        // Calculate regular and overtime hours
        $regular = min($totalHoursWorked, $threshold);
        $overtime = max(0, $totalHoursWorked - $threshold);
        $doubleTime = 0.0;

        // If employee has a double-time threshold, split overtime accordingly
        if ($doubleTimeThreshold && $totalHoursWorked > $doubleTimeThreshold) {
            $doubleTime = $totalHoursWorked - $doubleTimeThreshold;
            $overtime = max(0, $doubleTimeThreshold - $threshold);
        }

        Log::debug("[OvertimeCalculation] Employee {$employee->id}: Total={$totalHoursWorked}, Regular={$regular}, OT={$overtime}, DT={$doubleTime}");

        return [
            'regular' => round($regular, 2),
            'overtime' => round($overtime, 2),
            'double_time' => round($doubleTime, 2),
        ];
    }

    **
     * Calculate daily overtime (for states like California that require daily OT).
     *
     * @deprecated Use calculatePayPeriodOvertime() instead
     *
    public function calculateDailyOvertime(
        Employee $employee,
        float $dailyHours,
        bool $isWeekend = false
    ): array {
        if ($this->isOvertimeExempt($employee)) {
            return [
                'regular' => $dailyHours,
                'overtime' => 0.0,
                'double_time' => 0.0,
            ];
        }

        // Standard daily overtime thresholds
        $dailyOtThreshold = 8.0;
        $dailyDtThreshold = 12.0;

        $regular = min($dailyHours, $dailyOtThreshold);
        $overtime = 0.0;
        $doubleTime = 0.0;

        if ($dailyHours > $dailyOtThreshold) {
            $overtime = min($dailyHours - $dailyOtThreshold, $dailyDtThreshold - $dailyOtThreshold);
        }

        if ($dailyHours > $dailyDtThreshold) {
            $doubleTime = $dailyHours - $dailyDtThreshold;
        }

        return [
            'regular' => round($regular, 2),
            'overtime' => round($overtime, 2),
            'double_time' => round($doubleTime, 2),
        ];
    }

    **
     * Check if consecutive day overtime rules apply.
     *
     * @deprecated Use calculatePayPeriodOvertime() instead
     *
    public function hasConsecutiveDayOvertime(Employee $employee, int $consecutiveDaysWorked): bool
    {
        $rules = $this->getApplicableRules($employee);

        foreach ($rules->filter(fn (OvertimeRule $r) => $r->isConsecutiveDayRule()) as $rule) {
            if ($rule->consecutive_days_threshold && $consecutiveDaysWorked >= $rule->consecutive_days_threshold) {
                return true;
            }
        }

        return false;
    }
    */

    /**
     * Get the overtime rate multiplier for an employee.
     */
    public function getOvertimeMultiplier(Employee $employee): float
    {
        if ($employee->overtime_rate) {
            return (float) $employee->overtime_rate;
        }

        $rules = $this->getApplicableRules($employee);
        $weeklyRule = $rules->first(fn (OvertimeRule $r) => $r->isWeeklyThresholdRule());

        return $weeklyRule ? (float) $weeklyRule->multiplier : 1.5;
    }

    /**
     * Get the double-time rate multiplier.
     */
    public function getDoubleTimeMultiplier(): float
    {
        return 2.0;
    }
}
