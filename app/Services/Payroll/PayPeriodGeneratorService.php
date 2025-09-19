<?php

namespace App\Services\Payroll;

use App\Models\PayPeriod;
use App\Models\PayrollFrequency;
use App\Models\CompanySetup;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * PayPeriod Generator Service
 *
 * Handles complex payroll frequency calculations including:
 * - Dynamic "last day of month" calculations
 * - Weekend adjustments
 * - Month-end overflow scenarios
 * - Bi-weekly cycle tracking
 */
class PayPeriodGeneratorService
{
    /**
     * Special day codes
     */
    const LAST_DAY_OF_MONTH = 99;
    const FIRST_DAY_NEXT_MONTH = 98;

    /**
     * Generate pay periods for a given date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param PayrollFrequency|null $frequency If null, uses company default
     * @return Collection<PayPeriod>
     */
    public function generatePayPeriods(Carbon $startDate, Carbon $endDate, ?PayrollFrequency $frequency = null): Collection
    {
        if (!$frequency) {
            $frequency = $this->getCompanyPayrollFrequency();
        }

        $periods = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $payPeriods = $this->generatePeriodsForMonth($currentDate, $frequency);
            $periods = $periods->merge($payPeriods);
            $currentDate->addMonth();
        }

        // Remove duplicates and filter to requested range
        $uniquePeriods = $periods->unique(function ($period) {
            return $period->start_date->format('Y-m-d') . '|' . $period->end_date->format('Y-m-d');
        });

        return $uniquePeriods->filter(function ($period) use ($startDate, $endDate) {
            // Include periods that intersect with the requested range
            return $period->start_date->lte($endDate) && $period->end_date->gte($startDate);
        })->values(); // Reset keys after filtering
    }

    /**
     * Generate pay periods for a specific month
     */
    private function generatePeriodsForMonth(Carbon $month, PayrollFrequency $frequency): Collection
    {
        switch ($frequency->frequency_type) {
            case 'weekly':
                return $this->generateWeeklyPeriods($month, $frequency);
            case 'biweekly':
                return $this->generateBiweeklyPeriods($month, $frequency);
            case 'semimonthly':
                return $this->generateSemimonthlyPeriods($month, $frequency);
            case 'monthly':
                return $this->generateMonthlyPeriods($month, $frequency);
            default:
                throw new InvalidArgumentException("Unsupported frequency type: {$frequency->frequency_type}");
        }
    }

    /**
     * Generate weekly pay periods - FIXED TO PREVENT OVERLAPS
     *
     * This method is called per month but we need to ensure weekly periods
     * are continuous across month boundaries to prevent overlaps
     */
    private function generateWeeklyPeriods(Carbon $month, PayrollFrequency $frequency): Collection
    {
        $periods = collect();

        // For weekly periods, we need to find periods that INTERSECT with this month
        // rather than periods that START in this month
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        // Find the first Friday pay day that could affect this month
        // Go back to ensure we catch periods that start before the month but end in it
        $searchStart = $startOfMonth->copy()->subWeeks(2);
        $payDay = $searchStart->copy();

        // Find first pay day (Friday) on or after search start
        while ($payDay->dayOfWeek !== $frequency->weekly_day) {
            $payDay->addDay();
        }

        // Generate periods until we're well past the end of the month
        $searchEnd = $endOfMonth->copy()->addWeeks(2);

        while ($payDay->lte($searchEnd)) {
            $payDate = $this->adjustForWeekendsAndHolidays($payDay->copy(), $frequency);

            // Weekly period: Saturday to Friday (7 days)
            $periodEnd = $payDate->copy();
            $periodStart = $periodEnd->copy()->subDays(6); // 7-day period

            // Only include periods that intersect with this month
            if ($periodStart->lte($endOfMonth) && $periodEnd->gte($startOfMonth)) {
                $periods->push($this->createPayPeriod($periodStart, $periodEnd, $payDate));
            }

            $payDay->addWeek(); // Next weekly pay date
        }

        return $periods;
    }

    /**
     * Generate bi-weekly pay periods - THE COMPLEX ONE
     */
    private function generateBiweeklyPeriods(Carbon $month, PayrollFrequency $frequency): Collection
    {
        if (!$frequency->reference_start_date) {
            throw new InvalidArgumentException("Bi-weekly frequency requires reference_start_date");
        }

        $periods = collect();
        $referenceDate = Carbon::parse($frequency->reference_start_date);
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        // Calculate which bi-weekly cycle we're in
        $weeksSinceReference = $referenceDate->diffInWeeks($startOfMonth);

        // Find first bi-weekly pay date in this month
        $payDay = $referenceDate->copy();

        // Move to the correct bi-weekly cycle for this month
        $cyclesToAdd = floor($weeksSinceReference / 2) * 2;
        if ($weeksSinceReference % 2 === 1) {
            $cyclesToAdd += 2; // Move to next bi-weekly cycle
        }

        $payDay->addWeeks($cyclesToAdd);

        // Generate all bi-weekly periods that fall in this month
        while ($payDay->lte($endOfMonth->addMonth())) { // Look ahead to catch periods ending next month
            if ($payDay->gte($startOfMonth->subMonth())) { // Look back to catch periods starting last month
                $payDate = $this->adjustForWeekendsAndHolidays($payDay->copy(), $frequency);

                $periodEnd = $payDate->copy();
                $periodStart = $periodEnd->copy()->subDays(13); // 14-day period

                $periods->push($this->createPayPeriod($periodStart, $periodEnd, $payDate));
            }

            $payDay->addWeeks(2); // Next bi-weekly pay date
        }

        return $periods;
    }

    /**
     * Generate semi-monthly periods - HANDLES DYNAMIC LAST DAY
     */
    private function generateSemimonthlyPeriods(Carbon $month, PayrollFrequency $frequency): Collection
    {
        $periods = collect();

        $firstPayDay = $this->calculateActualPayDay($month, $frequency->first_pay_day, $frequency);
        $secondPayDay = $this->calculateActualPayDay($month, $frequency->second_pay_day, $frequency);

        // Ensure proper order (first should be earlier in month)
        if ($firstPayDay->gt($secondPayDay)) {
            [$firstPayDay, $secondPayDay] = [$secondPayDay, $firstPayDay];
        }

        // First period: 1st to middle of month
        $firstPeriodStart = $month->copy()->startOfMonth();
        $firstPeriodEnd = $firstPayDay->copy();
        $periods->push($this->createPayPeriod($firstPeriodStart, $firstPeriodEnd, $firstPayDay));

        // Second period: middle to end of month
        $secondPeriodStart = $firstPayDay->copy()->addDay();
        $secondPeriodEnd = $secondPayDay->copy();
        $periods->push($this->createPayPeriod($secondPeriodStart, $secondPeriodEnd, $secondPayDay));

        return $periods;
    }

    /**
     * Generate monthly periods - HANDLES DYNAMIC LAST DAY
     */
    private function generateMonthlyPeriods(Carbon $month, PayrollFrequency $frequency): Collection
    {
        $payDay = $this->calculateActualPayDay($month, $frequency->first_pay_day, $frequency);

        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        return collect([
            $this->createPayPeriod($periodStart, $periodEnd, $payDay)
        ]);
    }

    /**
     * Calculate actual pay day for a month - HANDLES DYNAMIC DATES
     *
     * This is where the magic happens for dynamic dates!
     */
    private function calculateActualPayDay(Carbon $month, int $payDayNumber, PayrollFrequency $frequency): Carbon
    {
        if ($payDayNumber === self::LAST_DAY_OF_MONTH) {
            // Special case: Last day of month (dynamic)
            $payDay = $month->copy()->endOfMonth();
        } elseif ($payDayNumber === self::FIRST_DAY_NEXT_MONTH) {
            // Special case: 1st day of next month (dynamic)
            $payDay = $month->copy()->endOfMonth()->addDay();
        } else {
            // Regular day number (1-31)
            $payDay = $month->copy()->startOfMonth()->addDays($payDayNumber - 1);

            // Handle month overflow (e.g., April 31st becomes April 30th)
            if ($payDay->month !== $month->month) {
                switch ($frequency->month_end_handling) {
                    case 'last_day_of_month':
                        $payDay = $month->copy()->endOfMonth();
                        break;
                    case 'first_day_next_month':
                        $payDay = $month->copy()->endOfMonth()->addDay();
                        break;
                    case 'exact_day':
                    default:
                        // Keep the overflow date as-is
                        break;
                }
            }
        }

        return $this->adjustForWeekendsAndHolidays($payDay, $frequency);
    }

    /**
     * Adjust pay date for weekends and holidays
     */
    private function adjustForWeekendsAndHolidays(Carbon $payDate, PayrollFrequency $frequency): Carbon
    {
        // Weekend adjustment
        if ($payDate->isWeekend()) {
            switch ($frequency->weekend_adjustment) {
                case 'previous_friday':
                    while ($payDate->isWeekend()) {
                        $payDate->subDay();
                    }
                    break;
                case 'next_monday':
                    while ($payDate->isWeekend()) {
                        $payDate->addDay();
                    }
                    break;
                case 'closest_weekday':
                    $before = $payDate->copy();
                    $after = $payDate->copy();

                    while ($before->isWeekend()) $before->subDay();
                    while ($after->isWeekend()) $after->addDay();

                    // Choose closest weekday
                    $payDate = ($payDate->diffInDays($before) <= $payDate->diffInDays($after)) ? $before : $after;
                    break;
                case 'none':
                default:
                    // No adjustment
                    break;
            }
        }

        // TODO: Holiday adjustment (when skip_holidays is true)
        // Would need a holidays table to check against

        return $payDate;
    }

    /**
     * Create a PayPeriod model instance
     */
    private function createPayPeriod(Carbon $startDate, Carbon $endDate, Carbon $payDate): PayPeriod
    {
        return new PayPeriod([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_processed' => false,
            'is_posted' => false,
        ]);
    }

    /**
     * Get the company's default payroll frequency
     */
    private function getCompanyPayrollFrequency(): PayrollFrequency
    {
        $companySetup = CompanySetup::first();

        if (!$companySetup || !$companySetup->payroll_frequency_id) {
            throw new InvalidArgumentException("Company payroll frequency not configured");
        }

        return $companySetup->payrollFrequency;
    }

    /**
     * Batch create pay periods and save to database
     */
    public function createAndSavePayPeriods(Carbon $startDate, Carbon $endDate, ?PayrollFrequency $frequency = null): Collection
    {
        $periods = $this->generatePayPeriods($startDate, $endDate, $frequency);

        $savedPeriods = collect();
        foreach ($periods as $period) {
            // Check if period already exists
            $existing = PayPeriod::where('start_date', $period->start_date)
                ->where('end_date', $period->end_date)
                ->first();

            if (!$existing) {
                $period->save();
                $savedPeriods->push($period);
            }
        }

        return $savedPeriods;
    }

    /**
     * Generate next X pay periods from today
     */
    public function generateNextPayPeriods(int $count, ?PayrollFrequency $frequency = null): Collection
    {
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addMonths($count * 2); // Generous range to ensure we get enough periods

        $periods = $this->generatePayPeriods($startDate, $endDate, $frequency);

        return $periods->take($count);
    }
}