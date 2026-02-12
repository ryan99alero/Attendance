<?php

namespace App\Services\Payroll;

use App\Models\CompanySetup;
use App\Models\PayPeriod;
use Carbon\Carbon;

/**
 * Service to generate names for PayPeriods based on configurable patterns
 *
 * Supported placeholders:
 * - {week_number}  : ISO week number of the start date (1-52/53)
 * - {year}         : 4-digit year of the start date
 * - {short_year}   : 2-digit year of the start date
 * - {start_date}   : Formatted start date (M j, Y)
 * - {end_date}     : Formatted end date (M j, Y)
 * - {start_month}  : Full month name of start date
 * - {end_month}    : Full month name of end date
 * - {start_month_short} : Abbreviated month name (Jan, Feb, etc.)
 * - {end_month_short}   : Abbreviated month name
 * - {start_day}    : Day of month for start date (1-31)
 * - {end_day}      : Day of month for end date (1-31)
 * - {sequence}     : Sequential period number within the year
 * - {quarter}      : Quarter number (Q1, Q2, Q3, Q4)
 */
class PayPeriodNamingService
{
    protected ?string $pattern = null;

    public function __construct()
    {
        $this->loadPattern();
    }

    /**
     * Load the naming pattern from company setup
     */
    protected function loadPattern(): void
    {
        $companySetup = CompanySetup::first();
        $this->pattern = $companySetup?->pay_period_naming_pattern ?? 'Week {week_number}, {year}';
    }

    /**
     * Generate a name for a pay period based on its dates
     */
    public function generateName(Carbon $startDate, Carbon $endDate): string
    {
        $name = $this->pattern;

        $replacements = [
            '{week_number}' => $startDate->isoWeek(),
            '{year}' => $startDate->year,
            '{short_year}' => $startDate->format('y'),
            '{start_date}' => $startDate->format('M j, Y'),
            '{end_date}' => $endDate->format('M j, Y'),
            '{start_month}' => $startDate->format('F'),
            '{end_month}' => $endDate->format('F'),
            '{start_month_short}' => $startDate->format('M'),
            '{end_month_short}' => $endDate->format('M'),
            '{start_day}' => $startDate->day,
            '{end_day}' => $endDate->day,
            '{sequence}' => $this->calculateSequence($startDate),
            '{quarter}' => 'Q'.$startDate->quarter,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $name);
    }

    /**
     * Calculate the sequence number for a period within its year
     */
    protected function calculateSequence(Carbon $startDate): int
    {
        // Count existing pay periods in this year before this start date
        $existingCount = PayPeriod::whereYear('start_date', $startDate->year)
            ->where('start_date', '<', $startDate)
            ->count();

        return $existingCount + 1;
    }

    /**
     * Get the current pattern
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Set a custom pattern (useful for previews)
     */
    public function setPattern(string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    /**
     * Get available placeholders with descriptions
     */
    public static function getAvailablePlaceholders(): array
    {
        return [
            '{week_number}' => 'ISO week number (1-52/53)',
            '{year}' => '4-digit year',
            '{short_year}' => '2-digit year',
            '{start_date}' => 'Formatted start date (M j, Y)',
            '{end_date}' => 'Formatted end date (M j, Y)',
            '{start_month}' => 'Full month name',
            '{end_month}' => 'Full end month name',
            '{start_month_short}' => 'Abbreviated month (Jan, Feb)',
            '{end_month_short}' => 'Abbreviated end month',
            '{start_day}' => 'Start day of month (1-31)',
            '{end_day}' => 'End day of month (1-31)',
            '{sequence}' => 'Sequential period number in year',
            '{quarter}' => 'Quarter (Q1, Q2, Q3, Q4)',
        ];
    }
}
