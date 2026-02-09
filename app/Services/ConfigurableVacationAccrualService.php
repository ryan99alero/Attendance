<?php

namespace App\Services;

use Exception;
use App\Models\Employee;
use App\Models\CompanySetup;
use App\Models\VacationPolicy;
use App\Models\VacationTransaction;
use App\Models\EmployeeVacationAssignment;
use App\Models\PayrollFrequency;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ConfigurableVacationAccrualService
{
    protected CompanySetup $companySetup;

    public function __construct()
    {
        $this->companySetup = CompanySetup::first() ?? new CompanySetup();
    }

    /**
     * Calculate vacation accrual for an employee based on company configuration
     */
    public function calculateEmployeeVacation(Employee $employee, Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::now();

        // Get employee's current vacation policy assignment
        $assignment = $this->getCurrentVacationAssignment($employee, $asOfDate);
        if (!$assignment) {
            return $this->getDefaultVacationData($employee);
        }

        $policy = $assignment->vacationPolicy;
        $method = $this->companySetup->vacation_accrual_method ?? 'anniversary';

        return match ($method) {
            'calendar_year' => $this->calculateCalendarYearAccrual($employee, $policy, $asOfDate),
            'pay_period' => $this->calculatePayPeriodAccrual($employee, $policy, $asOfDate),
            'anniversary' => $this->calculateAnniversaryAccrual($employee, $policy, $asOfDate),
            default => $this->getDefaultVacationData($employee)
        };
    }

    /**
     * Calendar Year Front-Load Method
     */
    protected function calculateCalendarYearAccrual(Employee $employee, VacationPolicy $policy, Carbon $asOfDate): array
    {
        $currentYear = $asOfDate->year;
        $awardDate = $this->companySetup->calendar_year_award_date;

        // Use configured award date or default to January 1st
        $yearAwardDate = $awardDate ?
            Carbon::create($currentYear, $awardDate->month, $awardDate->day) :
            Carbon::create($currentYear, 1, 1);

        // Calculate prorated amount if hired mid-year and proration is enabled
        $hireDate = $employee->date_of_hire;
        $totalYearHours = $policy->vacation_hours_per_year;

        if ($this->companySetup->calendar_year_prorate_partial &&
            $hireDate &&
            $hireDate->year == $currentYear &&
            $hireDate->gt($yearAwardDate)) {

            $daysInYear = $yearAwardDate->diffInDays($yearAwardDate->copy()->addYear()) + 1;
            $daysRemaining = $hireDate->diffInDays($yearAwardDate->copy()->addYear()) + 1;
            $totalYearHours = ($totalYearHours * $daysRemaining) / $daysInYear;
        }

        // Get transactions for this year
        $transactions = $this->getYearTransactions($employee, $currentYear);
        $accrued = $transactions->where('transaction_type', 'accrual')->sum('hours');
        $used = abs($transactions->where('transaction_type', 'usage')->sum('hours'));
        $adjustments = $transactions->where('transaction_type', 'adjustment')->sum('hours');

        return [
            'accrual_method' => 'calendar_year',
            'current_period' => $currentYear,
            'award_date' => $yearAwardDate,
            'annual_entitlement' => $totalYearHours,
            'accrued_this_period' => $accrued,
            'used_this_period' => $used,
            'adjustments' => $adjustments,
            'current_balance' => $accrued - $used + $adjustments,
            'proration_applied' => $this->companySetup->calendar_year_prorate_partial && $hireDate && $hireDate->year == $currentYear,
            'policy' => $policy->toArray(),
        ];
    }

    /**
     * Pay Period Accrual Method
     */
    protected function calculatePayPeriodAccrual(Employee $employee, VacationPolicy $policy, Carbon $asOfDate): array
    {
        $payrollFreq = $this->companySetup->payrollFrequency;
        if (!$payrollFreq) {
            throw new Exception('Payroll frequency not configured for pay period vacation accrual');
        }

        // Use configured hours per period or calculate from annual entitlement
        $hoursPerPeriod = $this->companySetup->pay_period_hours_per_period;
        if (!$hoursPerPeriod) {
            $annualHours = $policy->vacation_hours_per_year;
            $periodsPerYear = $this->getPeriodsPerYear($payrollFreq->frequency_name);
            $hoursPerPeriod = $annualHours / $periodsPerYear;
        }

        $hireDate = $employee->date_of_hire ?? $asOfDate;
        $waitingPeriods = $this->companySetup->pay_period_waiting_periods ?? 0;

        // Calculate periods worked, accounting for waiting period
        $totalPeriodsWorked = $this->calculatePeriodsWorked($hireDate, $asOfDate, $payrollFreq);
        $eligiblePeriods = max(0, $totalPeriodsWorked - $waitingPeriods);

        // Calculate total accrued based on whether they accrue immediately or after waiting
        $totalAccrued = 0;
        if ($this->companySetup->pay_period_accrue_immediately || $totalPeriodsWorked > $waitingPeriods) {
            $totalAccrued = $eligiblePeriods * $hoursPerPeriod;
        }

        // Apply cap if configured
        if ($this->companySetup->max_accrual_balance) {
            $totalAccrued = min($totalAccrued, $this->companySetup->max_accrual_balance);
        }

        // Get actual transactions
        $transactions = $this->getAllTransactions($employee);
        $used = abs($transactions->where('transaction_type', 'usage')->sum('hours'));
        $adjustments = $transactions->where('transaction_type', 'adjustment')->sum('hours');

        return [
            'accrual_method' => 'pay_period',
            'current_period' => $this->getCurrentPayPeriod($asOfDate, $payrollFreq),
            'hours_per_period' => $hoursPerPeriod,
            'total_periods_worked' => $totalPeriodsWorked,
            'waiting_periods' => $waitingPeriods,
            'eligible_periods' => $eligiblePeriods,
            'total_accrued' => $totalAccrued,
            'used_total' => $used,
            'adjustments' => $adjustments,
            'current_balance' => $totalAccrued - $used + $adjustments,
            'accrue_immediately' => $this->companySetup->pay_period_accrue_immediately,
            'policy' => $policy->toArray(),
        ];
    }

    /**
     * Anniversary Date Step Blocks Method
     */
    protected function calculateAnniversaryAccrual(Employee $employee, VacationPolicy $policy, Carbon $asOfDate): array
    {
        if (!$employee->date_of_hire) {
            return $this->getDefaultVacationData($employee);
        }

        $hireDate = $employee->date_of_hire;
        $yearsOfService = $hireDate->diffInYears($asOfDate);

        // Apply company-specific anniversary settings
        $hasFirstYearWaiting = $this->companySetup->anniversary_first_year_waiting_period;
        $allowsPartialYear = $this->companySetup->anniversary_allow_partial_year;
        $awardOnAnniversary = $this->companySetup->anniversary_award_on_anniversary;
        $maxDaysCap = $this->companySetup->anniversary_max_days_cap;

        // Get last anniversary date
        $lastAnniversary = $hireDate->copy()->addYears($yearsOfService);
        if ($lastAnniversary->gt($asOfDate)) {
            $lastAnniversary->subYear();
            $yearsOfService--;
        }

        $nextAnniversary = $lastAnniversary->copy()->addYear();

        // Check if employee is eligible for vacation
        $isEligible = true;
        if ($hasFirstYearWaiting && $yearsOfService < 1) {
            $isEligible = $allowsPartialYear;
        }

        // Calculate annual entitlement
        $annualEntitlement = $policy->vacation_hours_per_year;
        if ($maxDaysCap) {
            $maxHours = $maxDaysCap * 8; // Convert days to hours
            $annualEntitlement = min($annualEntitlement, $maxHours);
        }

        // Get all transactions for current anniversary year
        $anniversaryStart = $lastAnniversary;
        $anniversaryEnd = $nextAnniversary->copy()->subDay();

        $transactions = VacationTransaction::where('employee_id', $employee->id)
            ->whereBetween('transaction_date', [$anniversaryStart, $anniversaryEnd])
            ->get();

        $accrued = $transactions->where('transaction_type', 'accrual')->sum('hours');
        $used = abs($transactions->where('transaction_type', 'usage')->sum('hours'));
        $adjustments = $transactions->where('transaction_type', 'adjustment')->sum('hours');

        // Calculate partial year accrual if allowed and in first year
        $partialYearAccrual = 0;
        if ($allowsPartialYear && $yearsOfService < 1 && $asOfDate->lt($nextAnniversary)) {
            $daysInYear = $hireDate->diffInDays($nextAnniversary);
            $daysWorked = $hireDate->diffInDays($asOfDate);
            $partialYearAccrual = ($annualEntitlement * $daysWorked) / $daysInYear;
        }

        return [
            'accrual_method' => 'anniversary',
            'years_of_service' => $yearsOfService,
            'last_anniversary' => $lastAnniversary,
            'next_anniversary' => $nextAnniversary,
            'annual_entitlement' => $annualEntitlement,
            'is_eligible' => $isEligible,
            'has_first_year_waiting' => $hasFirstYearWaiting,
            'allows_partial_year' => $allowsPartialYear,
            'award_on_anniversary' => $awardOnAnniversary,
            'partial_year_accrual' => $partialYearAccrual,
            'accrued_this_year' => $accrued,
            'used_this_year' => $used,
            'adjustments' => $adjustments,
            'current_balance' => $accrued - $used + $adjustments + $partialYearAccrual,
            'max_days_cap' => $maxDaysCap,
            'policy' => $policy->toArray(),
        ];
    }

    /**
     * Process vacation accrual for an employee
     */
    public function processVacationAccrual(Employee $employee, Carbon $date = null): VacationTransaction
    {
        $date = $date ?? Carbon::now();
        $method = $this->companySetup->vacation_accrual_method ?? 'anniversary';

        return match ($method) {
            'calendar_year' => $this->processCalendarYearAccrual($employee, $date),
            'pay_period' => $this->processPayPeriodAccrual($employee, $date),
            'anniversary' => $this->processAnniversaryAccrual($employee, $date),
            default => throw new Exception("Unknown vacation accrual method: $method")
        };
    }

    /**
     * Get current vacation policy assignment for employee
     */
    protected function getCurrentVacationAssignment(Employee $employee, Carbon $date): ?EmployeeVacationAssignment
    {
        return EmployeeVacationAssignment::where('employee_id', $employee->id)
            ->active()
            ->effectiveOn($date)
            ->with('vacationPolicy')
            ->first();
    }

    /**
     * Get vacation policy for employee based on tenure
     */
    public function getVacationPolicyForEmployee(Employee $employee, Carbon $date = null): ?VacationPolicy
    {
        $date = $date ?? Carbon::now();

        if (!$employee->date_of_hire) {
            return null;
        }

        $yearsOfService = $employee->date_of_hire->diffInYears($date);

        return VacationPolicy::active()
            ->forTenure($yearsOfService)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Assign vacation policy to employee
     */
    public function assignVacationPolicy(Employee $employee, VacationPolicy $policy, Carbon $effectiveDate = null): EmployeeVacationAssignment
    {
        $effectiveDate = $effectiveDate ?? Carbon::now();

        // End any current assignments
        EmployeeVacationAssignment::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereNull('end_date')
            ->update([
                'end_date' => $effectiveDate->copy()->subDay(),
                'is_active' => false,
            ]);

        // Create new assignment
        return EmployeeVacationAssignment::create([
            'employee_id' => $employee->id,
            'vacation_policy_id' => $policy->id,
            'effective_date' => $effectiveDate,
            'is_active' => true,
        ]);
    }

    /**
     * Helper methods
     */
    protected function getYearTransactions(Employee $employee, int $year)
    {
        $start = Carbon::create($year, 1, 1);
        $end = Carbon::create($year, 12, 31);

        return VacationTransaction::where('employee_id', $employee->id)
            ->whereBetween('transaction_date', [$start, $end])
            ->get();
    }

    protected function getAllTransactions(Employee $employee)
    {
        return VacationTransaction::where('employee_id', $employee->id)->get();
    }

    protected function getPeriodsPerYear(string $frequency): int
    {
        return match ($frequency) {
            'weekly' => 52,
            'biweekly' => 26,
            'monthly' => 12,
            'semimonthly' => 24,
            default => 26 // default to biweekly
        };
    }

    protected function calculatePeriodsWorked(Carbon $hireDate, Carbon $asOfDate, PayrollFrequency $payrollFreq): int
    {
        $weeks = $hireDate->diffInWeeks($asOfDate);

        return match ($payrollFreq->frequency_name) {
            'weekly' => $weeks,
            'biweekly' => intval($weeks / 2),
            'monthly' => $hireDate->diffInMonths($asOfDate),
            'semimonthly' => $hireDate->diffInMonths($asOfDate) * 2,
            default => intval($weeks / 2)
        };
    }

    protected function getCurrentPayPeriod(Carbon $date, PayrollFrequency $payrollFreq): string
    {
        return match ($payrollFreq->frequency_name) {
            'weekly' => $date->format('Y-W'),
            'biweekly' => $date->format('Y') . '-B' . intval($date->weekOfYear / 2),
            'monthly' => $date->format('Y-m'),
            'semimonthly' => $date->format('Y-m') . ($date->day <= 15 ? '-1' : '-2'),
            default => $date->format('Y-m')
        };
    }

    protected function getDefaultVacationData(Employee $employee): array
    {
        return [
            'accrual_method' => 'none',
            'current_balance' => 0,
            'message' => 'No vacation policy assigned',
        ];
    }

    protected function processCalendarYearAccrual(Employee $employee, Carbon $date): VacationTransaction
    {
        // Implementation for calendar year processing
        throw new Exception('Calendar year accrual processing not yet implemented');
    }

    protected function processPayPeriodAccrual(Employee $employee, Carbon $date): VacationTransaction
    {
        // Implementation for pay period processing
        throw new Exception('Pay period accrual processing not yet implemented');
    }

    protected function processAnniversaryAccrual(Employee $employee, Carbon $date): VacationTransaction
    {
        // Use existing VacationAccrualService logic
        $vacationService = new VacationAccrualService();
        $balance = $vacationService->processAnniversaryAccrual($employee, $date);

        // Create transaction record
        $policy = $this->getVacationPolicyForEmployee($employee, $date);
        $hoursAwarded = $policy ? $policy->vacation_hours_per_year : 0;

        return VacationTransaction::create([
            'employee_id' => $employee->id,
            'transaction_type' => 'accrual',
            'hours' => $hoursAwarded,
            'transaction_date' => $date,
            'effective_date' => $date,
            'accrual_period' => $date->year . '-Anniversary',
            'description' => "Anniversary accrual - Year " . ($employee->date_of_hire->diffInYears($date) + 1),
            'metadata' => [
                'years_of_service' => $employee->date_of_hire->diffInYears($date),
                'policy_id' => $policy?->id,
                'method' => 'anniversary'
            ]
        ]);
    }
}