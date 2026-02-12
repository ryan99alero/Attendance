<?php

namespace App\Console\Commands;

use App\Models\CompanySetup;
use App\Models\Employee;
use App\Models\VacationBalance;
use App\Models\VacationPolicy;
use App\Models\VacationTransaction;
use App\Services\ConfigurableVacationAccrualService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessVacationAccruals extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'vacation:process-accruals
                           {--date= : Process for specific date (YYYY-MM-DD)}
                           {--employee= : Process for specific employee ID}
                           {--force : Force reprocessing even if already processed}
                           {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Process vacation accruals for employees based on anniversary dates and company policy';

    protected ?ConfigurableVacationAccrualService $accrualService = null;

    protected ?CompanySetup $companySetup = null;

    public function __construct()
    {
        parent::__construct();
        // Lazy load to avoid DB queries during kernel boot (breaks migrations)
    }

    protected function getAccrualService(): ConfigurableVacationAccrualService
    {
        if ($this->accrualService === null) {
            $this->accrualService = new ConfigurableVacationAccrualService;
        }

        return $this->accrualService;
    }

    protected function getCompanySetup(): CompanySetup
    {
        if ($this->companySetup === null) {
            try {
                $this->companySetup = CompanySetup::first() ?? new CompanySetup;
            } catch (\Exception $e) {
                $this->companySetup = new CompanySetup;
            }
        }

        return $this->companySetup;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $processDate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::now();
        $specificEmployee = $this->option('employee');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info('🗓️  Processing vacation accruals for: '.$processDate->toDateString());

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }

        if ($this->getCompanySetup()->vacation_accrual_method !== 'anniversary') {
            $this->error("❌ Company vacation accrual method is not set to 'anniversary'. Current method: ".($this->getCompanySetup()->vacation_accrual_method ?? 'not set'));

            return 1;
        }

        // Get employees to process
        $employees = $this->getEmployeesToProcess($processDate, $specificEmployee);

        if ($employees->isEmpty()) {
            $this->info('✅ No employees need vacation accrual processing for '.$processDate->toDateString());

            return 0;
        }

        $this->info('👥 Found '.$employees->count().' employee(s) to process');

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($employees->count());
        $progressBar->start();

        foreach ($employees as $employee) {
            try {
                $result = $this->processEmployeeAccrual($employee, $processDate, $force, $dryRun);

                if ($result['processed']) {
                    $processed++;
                    $this->newLine();
                    $this->info("✅ {$employee->full_names}: +{$result['hours']} hours ({$result['days']} days)");
                } else {
                    $skipped++;
                    if ($this->getOutput()->isVerbose()) {
                        $this->newLine();
                        $this->comment("⏭️  {$employee->full_names}: {$result['reason']}");
                    }
                }

            } catch (Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("❌ {$employee->full_names}: ".$e->getMessage());
                Log::error("Vacation accrual processing error for employee {$employee->id}: ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('📊 Processing Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Processed', $processed],
                ['⏭️  Skipped', $skipped],
                ['❌ Errors', $errors],
                ['📋 Total', $employees->count()],
            ]
        );

        if ($dryRun && $processed > 0) {
            $this->warn('🧪 This was a dry run. Run without --dry-run to actually process accruals.');
        }

        return $errors > 0 ? 1 : 0;
    }

    /**
     * Get employees that need vacation accrual processing
     */
    protected function getEmployeesToProcess(Carbon $processDate, ?int $specificEmployee)
    {
        $query = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('date_of_hire')
            ->with(['vacationAssignments.vacationPolicy', 'vacationTransactions']);

        if ($specificEmployee) {
            $query->where('id', $specificEmployee);
        } else {
            // Only get employees whose anniversary is today or overdue
            $query->where(function ($q) use ($processDate) {
                $q->whereRaw('DATE(DATE_ADD(date_of_hire, INTERVAL FLOOR(DATEDIFF(?, date_of_hire) / 365.25) YEAR)) <= ?',
                    [$processDate->toDateString(), $processDate->toDateString()])
                    ->whereRaw('FLOOR(DATEDIFF(?, date_of_hire) / 365.25) >= 1', [$processDate->toDateString()]);
            });
        }

        return $query->get();
    }

    /**
     * Process vacation accrual for a specific employee
     */
    protected function processEmployeeAccrual(Employee $employee, Carbon $processDate, bool $force, bool $dryRun): array
    {
        // Check if employee has been with company for at least 1 year
        $yearsOfService = $employee->date_of_hire->diffInYears($processDate);
        if ($yearsOfService < 1 && ! $this->getCompanySetup()->anniversary_allow_partial_year) {
            return [
                'processed' => false,
                'reason' => 'Not eligible yet (less than 1 year of service)',
                'hours' => 0,
                'days' => 0,
            ];
        }

        // Calculate anniversary date for this year
        $anniversaryThisYear = $employee->date_of_hire->copy()
            ->year($processDate->year);

        // If anniversary hasn't occurred yet this year, use last year's anniversary
        if ($anniversaryThisYear->gt($processDate)) {
            $anniversaryThisYear->subYear();
        }

        // Check if already processed for this anniversary
        if (! $force) {
            $existingTransaction = VacationTransaction::where('employee_id', $employee->id)
                ->where('transaction_type', 'accrual')
                ->whereDate('transaction_date', $anniversaryThisYear)
                ->where('accrual_period', $anniversaryThisYear->year.'-Anniversary')
                ->exists();

            if ($existingTransaction) {
                return [
                    'processed' => false,
                    'reason' => 'Already processed for '.$anniversaryThisYear->toDateString(),
                    'hours' => 0,
                    'days' => 0,
                ];
            }
        }

        // Get vacation policy for this employee
        $policy = $this->getVacationPolicyForEmployee($employee, $anniversaryThisYear);
        if (! $policy) {
            return [
                'processed' => false,
                'reason' => 'No vacation policy found',
                'hours' => 0,
                'days' => 0,
            ];
        }

        // Calculate vacation hours to award
        $vacationHours = $policy->vacation_hours_per_year;
        $vacationDays = $policy->vacation_days_per_year;

        // Apply company max days cap if configured
        if ($this->getCompanySetup()->anniversary_max_days_cap) {
            $maxHours = $this->getCompanySetup()->anniversary_max_days_cap * 8;
            if ($vacationHours > $maxHours) {
                $vacationHours = $maxHours;
                $vacationDays = $this->getCompanySetup()->anniversary_max_days_cap;
            }
        }

        if ($dryRun) {
            return [
                'processed' => true,
                'reason' => 'Would award vacation',
                'hours' => $vacationHours,
                'days' => $vacationDays,
            ];
        }

        // Create the accrual transaction
        DB::transaction(function () use ($employee, $anniversaryThisYear, $processDate, $vacationHours, $vacationDays, $policy, $yearsOfService) {
            // Create vacation transaction
            VacationTransaction::create([
                'employee_id' => $employee->id,
                'transaction_type' => 'accrual',
                'hours' => $vacationHours,
                'transaction_date' => $processDate,
                'effective_date' => $anniversaryThisYear,
                'accrual_period' => $anniversaryThisYear->year.'-Anniversary',
                'description' => "Anniversary accrual - Year {$yearsOfService} ({$vacationDays} days)",
                'metadata' => [
                    'years_of_service' => $yearsOfService,
                    'policy_id' => $policy->id,
                    'policy_name' => $policy->policy_name,
                    'anniversary_date' => $anniversaryThisYear->toDateString(),
                    'method' => 'anniversary',
                    'processed_by' => 'system',
                ],
            ]);

            // Update or create vacation balance
            $this->updateVacationBalance($employee, $vacationHours, $vacationDays, $anniversaryThisYear, $yearsOfService, $policy);
        });

        return [
            'processed' => true,
            'reason' => 'Successfully processed',
            'hours' => $vacationHours,
            'days' => $vacationDays,
        ];
    }

    /**
     * Get vacation policy for employee based on years of service
     */
    protected function getVacationPolicyForEmployee(Employee $employee, Carbon $anniversaryDate): ?VacationPolicy
    {
        $yearsOfService = $employee->date_of_hire->diffInYears($anniversaryDate);

        return VacationPolicy::active()
            ->where('min_tenure_years', '<=', $yearsOfService)
            ->where(function ($query) use ($yearsOfService) {
                $query->whereNull('max_tenure_years')
                    ->orWhere('max_tenure_years', '>=', $yearsOfService);
            })
            ->orderBy('min_tenure_years', 'desc')
            ->first();
    }

    /**
     * Update vacation balance for employee
     */
    protected function updateVacationBalance(Employee $employee, float $vacationHours, float $vacationDays, Carbon $anniversaryDate, int $yearsOfService, VacationPolicy $policy)
    {
        $balance = VacationBalance::firstOrCreate(
            ['employee_id' => $employee->id],
            [
                'accrual_rate' => 0,
                'accrued_hours' => 0,
                'used_hours' => 0,
                'carry_over_hours' => 0,
                'cap_hours' => $this->getCompanySetup()->max_accrual_balance ?? ($policy->vacation_hours_per_year * 2),
                'is_anniversary_based' => true,
                'policy_effective_date' => $anniversaryDate,
            ]
        );

        // Calculate next anniversary
        $nextAnniversary = $anniversaryDate->copy()->addYear();

        $balance->update([
            'accrued_hours' => $balance->accrued_hours + $vacationHours,
            'accrual_year' => $yearsOfService,
            'last_anniversary_date' => $anniversaryDate,
            'next_anniversary_date' => $nextAnniversary,
            'annual_days_earned' => $vacationDays,
            'current_year_awarded' => $vacationHours,
            'accrual_history' => $this->updateAccrualHistory($balance->accrual_history, [
                'date' => $anniversaryDate->toDateString(),
                'years_of_service' => $yearsOfService,
                'days_awarded' => $vacationDays,
                'hours_awarded' => $vacationHours,
                'policy_id' => $policy->id,
                'type' => 'anniversary_accrual',
            ]),
        ]);
    }

    /**
     * Update accrual history JSON
     */
    protected function updateAccrualHistory(?string $currentHistory, array $newEntry): string
    {
        $history = $currentHistory ? json_decode($currentHistory, true) : [];
        $history[] = $newEntry;

        // Keep only last 10 entries
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }

        return json_encode($history);
    }
}
