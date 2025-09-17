<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\VacationBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VacationAccrualService
{
    /**
     * Rand Graphics Vacation Policy:
     * - Years 1-5: 10 days annually
     * - Year 6: 11 days, Year 7: 12 days, etc. (incremental)
     * - Year 10+: Caps at 15 days forever
     * - Credited on anniversary date
     */
    public function calculateVacationDays(int $yearsOfService): float
    {
        if ($yearsOfService <= 5) {
            return 10.0; // Years 1-5: 10 days
        } elseif ($yearsOfService <= 9) {
            return 10.0 + ($yearsOfService - 5); // Year 6=11, 7=12, 8=13, 9=14
        } else {
            return 15.0; // Year 10+ caps at 15 days
        }
    }

    /**
     * Convert vacation days to hours (8 hours per day)
     */
    public function daysToHours(float $days): float
    {
        return $days * 8.0;
    }

    /**
     * Calculate years of service based on hire date and current date
     */
    public function calculateYearsOfService(Carbon $hireDate, Carbon $asOfDate = null): int
    {
        $asOfDate = $asOfDate ?? Carbon::now();
        return $hireDate->diffInYears($asOfDate);
    }

    /**
     * Get next anniversary date
     */
    public function getNextAnniversaryDate(Carbon $hireDate, Carbon $asOfDate = null): Carbon
    {
        $asOfDate = $asOfDate ?? Carbon::now();
        $nextAnniversary = $hireDate->copy()->year($asOfDate->year);

        // If this year's anniversary has passed, get next year's
        if ($nextAnniversary->lt($asOfDate)) {
            $nextAnniversary->addYear();
        }

        return $nextAnniversary;
    }

    /**
     * Process anniversary vacation accrual for an employee
     */
    public function processAnniversaryAccrual(Employee $employee, Carbon $anniversaryDate = null): VacationBalance
    {
        if (!$employee->date_of_hire) {
            throw new \Exception("Employee {$employee->id} has no hire date set.");
        }

        $anniversaryDate = $anniversaryDate ?? Carbon::now();
        $yearsOfService = $this->calculateYearsOfService($employee->date_of_hire, $anniversaryDate);

        // Don't accrue until after first year
        if ($yearsOfService < 1) {
            Log::info("Employee {$employee->id} not eligible for vacation accrual yet (hired: {$employee->date_of_hire})");
            return $this->getOrCreateVacationBalance($employee);
        }

        $vacationDays = $this->calculateVacationDays($yearsOfService);
        $vacationHours = $this->daysToHours($vacationDays);

        $balance = $this->getOrCreateVacationBalance($employee);

        // Award vacation on anniversary
        $balance->update([
            'accrual_year' => $yearsOfService,
            'last_anniversary_date' => $anniversaryDate,
            'next_anniversary_date' => $this->getNextAnniversaryDate($employee->date_of_hire, $anniversaryDate),
            'annual_days_earned' => $vacationDays,
            'current_year_awarded' => $vacationHours,
            'accrued_hours' => $balance->accrued_hours + $vacationHours,
            'accrual_history' => $this->updateAccrualHistory($balance->accrual_history, [
                'date' => $anniversaryDate->toDateString(),
                'years_of_service' => $yearsOfService,
                'days_awarded' => $vacationDays,
                'hours_awarded' => $vacationHours,
                'type' => 'anniversary_accrual'
            ])
        ]);

        Log::info("Anniversary accrual processed for Employee {$employee->id}: {$vacationDays} days ({$vacationHours} hours)");

        return $balance->fresh();
    }

    /**
     * Get or create vacation balance record for employee
     */
    public function getOrCreateVacationBalance(Employee $employee): VacationBalance
    {
        return VacationBalance::firstOrCreate(
            ['employee_id' => $employee->id],
            [
                'accrual_rate' => 0, // Not used in anniversary system
                'accrued_hours' => 0,
                'used_hours' => 0,
                'carry_over_hours' => 0,
                'cap_hours' => $this->daysToHours(15), // Max 15 days cap
                'accrual_year' => 1,
                'is_anniversary_based' => true,
                'policy_effective_date' => Carbon::now(),
                'next_anniversary_date' => $employee->date_of_hire ?
                    $this->getNextAnniversaryDate($employee->date_of_hire) : null,
            ]
        );
    }

    /**
     * Update accrual history JSON log
     */
    private function updateAccrualHistory(?string $currentHistory, array $newEntry): string
    {
        $history = $currentHistory ? json_decode($currentHistory, true) : [];
        $history[] = $newEntry;

        // Keep only last 10 entries to prevent bloat
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }

        return json_encode($history);
    }

    /**
     * Check if employee is due for anniversary accrual
     */
    public function isDueForAnniversaryAccrual(Employee $employee): bool
    {
        if (!$employee->date_of_hire) {
            return false;
        }

        $balance = $this->getOrCreateVacationBalance($employee);
        $nextAnniversary = $this->getNextAnniversaryDate($employee->date_of_hire);

        return Carbon::now()->gte($nextAnniversary) &&
               (!$balance->last_anniversary_date ||
                $balance->last_anniversary_date->lt($nextAnniversary));
    }

    /**
     * Process all employees due for anniversary accrual
     */
    public function processAllDueAnniversaries(): array
    {
        $processed = [];

        Employee::whereNotNull('date_of_hire')
            ->where('is_active', true)
            ->get()
            ->each(function (Employee $employee) use (&$processed) {
                if ($this->isDueForAnniversaryAccrual($employee)) {
                    $balance = $this->processAnniversaryAccrual($employee);
                    $processed[] = [
                        'employee_id' => $employee->id,
                        'name' => $employee->full_names,
                        'days_awarded' => $balance->annual_days_earned,
                        'total_balance' => $balance->accrued_hours / 8,
                    ];
                }
            });

        return $processed;
    }
}