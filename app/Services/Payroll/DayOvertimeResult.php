<?php

namespace App\Services\Payroll;

use App\Models\HolidayInstance;
use App\Models\OvertimeRule;
use Carbon\Carbon;

/**
 * Represents the overtime calculation result for a single day.
 */
class DayOvertimeResult
{
    public Carbon $date;

    public float $totalHours;

    public float $regularHours;

    public float $overtimeHours;

    public float $doubleTimeHours;

    public float $holidayHours;

    public ?OvertimeRule $appliedRule = null;

    public ?HolidayInstance $holidayInstance = null;

    public ?string $reason = null;

    public array $context = [];

    public function __construct(Carbon $date, float $totalHours = 0.0)
    {
        $this->date = $date;
        $this->totalHours = $totalHours;
        $this->regularHours = 0.0;
        $this->overtimeHours = 0.0;
        $this->doubleTimeHours = 0.0;
        $this->holidayHours = 0.0;
    }

    /**
     * Create a result where all hours are regular.
     */
    public static function allRegular(Carbon $date, float $hours, ?string $reason = null): self
    {
        $result = new self($date, $hours);
        $result->regularHours = $hours;
        $result->reason = $reason ?? 'All hours regular';

        return $result;
    }

    /**
     * Create a result where all hours are overtime.
     */
    public static function allOvertime(Carbon $date, float $hours, OvertimeRule $rule, ?string $reason = null): self
    {
        $result = new self($date, $hours);
        $result->overtimeHours = $hours;
        $result->appliedRule = $rule;
        $result->reason = $reason ?? "Overtime: {$rule->rule_name}";

        return $result;
    }

    /**
     * Create a result where all hours are double-time.
     */
    public static function allDoubleTime(Carbon $date, float $hours, OvertimeRule $rule, ?string $reason = null): self
    {
        $result = new self($date, $hours);
        $result->doubleTimeHours = $hours;
        $result->appliedRule = $rule;
        $result->reason = $reason ?? "Double-time: {$rule->rule_name}";

        return $result;
    }

    /**
     * Create a result where all hours are holiday pay.
     */
    public static function allHoliday(Carbon $date, float $hours, HolidayInstance $holiday, ?string $reason = null): self
    {
        $result = new self($date, $hours);
        $result->holidayHours = $hours;
        $result->holidayInstance = $holiday;
        $result->reason = $reason ?? "Holiday: {$holiday->name}";

        return $result;
    }

    /**
     * Create a result for an exempt employee (all regular, no overtime).
     */
    public static function exempt(Carbon $date, float $hours): self
    {
        $result = new self($date, $hours);
        $result->regularHours = $hours;
        $result->reason = 'Overtime exempt employee';

        return $result;
    }

    /**
     * Set the hours breakdown for this day.
     */
    public function setBreakdown(
        float $regular,
        float $overtime = 0.0,
        float $doubleTime = 0.0,
        float $holiday = 0.0
    ): self {
        $this->regularHours = $regular;
        $this->overtimeHours = $overtime;
        $this->doubleTimeHours = $doubleTime;
        $this->holidayHours = $holiday;

        return $this;
    }

    /**
     * Set the rule that was applied.
     */
    public function withRule(?OvertimeRule $rule): self
    {
        $this->appliedRule = $rule;

        return $this;
    }

    /**
     * Set the holiday instance.
     */
    public function withHoliday(?HolidayInstance $holiday): self
    {
        $this->holidayInstance = $holiday;

        return $this;
    }

    /**
     * Set the reason for this calculation.
     */
    public function withReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Add context data.
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Get the day of week (0 = Sunday, 6 = Saturday).
     */
    public function getDayOfWeek(): int
    {
        return (int) $this->date->dayOfWeek;
    }

    /**
     * Check if this day has any overtime or special hours.
     */
    public function hasSpecialHours(): bool
    {
        return $this->overtimeHours > 0
            || $this->doubleTimeHours > 0
            || $this->holidayHours > 0;
    }

    /**
     * Get the total non-regular hours.
     */
    public function getNonRegularHours(): float
    {
        return $this->overtimeHours + $this->doubleTimeHours + $this->holidayHours;
    }

    /**
     * Get the effective multiplier for overtime hours.
     */
    public function getOvertimeMultiplier(): float
    {
        return $this->appliedRule?->multiplier ?? 1.5;
    }

    /**
     * Get the effective multiplier for double-time hours.
     */
    public function getDoubleTimeMultiplier(): float
    {
        return $this->appliedRule?->double_time_multiplier ?? 2.0;
    }

    /**
     * Get the effective multiplier for holiday hours.
     */
    public function getHolidayMultiplier(): float
    {
        return $this->holidayInstance?->holiday_multiplier ?? 2.0;
    }

    /**
     * Calculate the total equivalent regular hours (for pay purposes).
     */
    public function getTotalEquivalentHours(): float
    {
        return $this->regularHours
            + ($this->overtimeHours * $this->getOvertimeMultiplier())
            + ($this->doubleTimeHours * $this->getDoubleTimeMultiplier())
            + ($this->holidayHours * $this->getHolidayMultiplier());
    }

    /**
     * Convert to an array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'day_of_week' => $this->getDayOfWeek(),
            'day_name' => $this->date->format('l'),
            'total_hours' => round($this->totalHours, 2),
            'regular_hours' => round($this->regularHours, 2),
            'overtime_hours' => round($this->overtimeHours, 2),
            'double_time_hours' => round($this->doubleTimeHours, 2),
            'holiday_hours' => round($this->holidayHours, 2),
            'applied_rule' => $this->appliedRule?->rule_name,
            'applied_rule_id' => $this->appliedRule?->id,
            'holiday' => $this->holidayInstance?->name,
            'holiday_instance_id' => $this->holidayInstance?->id,
            'reason' => $this->reason,
            'context' => $this->context,
        ];
    }

    /**
     * Create a debug summary string.
     */
    public function __toString(): string
    {
        $parts = ["{$this->date->toDateString()}:"];

        if ($this->regularHours > 0) {
            $parts[] = "{$this->regularHours}h reg";
        }
        if ($this->overtimeHours > 0) {
            $parts[] = "{$this->overtimeHours}h OT";
        }
        if ($this->doubleTimeHours > 0) {
            $parts[] = "{$this->doubleTimeHours}h DT";
        }
        if ($this->holidayHours > 0) {
            $parts[] = "{$this->holidayHours}h holiday";
        }
        if ($this->reason) {
            $parts[] = "({$this->reason})";
        }

        return implode(' ', $parts);
    }
}
