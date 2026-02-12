<?php

namespace App\Console\Commands;

use App\Models\CompanySetup;
use App\Services\Payroll\PayPeriodGeneratorService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class GeneratePayPeriods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:generate-periods
                           {--months=1 : Number of months to generate ahead}
                           {--current-month : Generate only for current month}
                           {--weeks=4 : Number of weeks ahead from current week}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate PayPeriods: --current-month, --weeks=N, or --months=N (prevents duplicates)';

    /**
     * Execute the console command.
     */
    public function handle(PayPeriodGeneratorService $generator)
    {
        $this->info('🕒 Starting PayPeriod generation...');

        // Get company setup to ensure payroll frequency is configured
        $companySetup = CompanySetup::first();

        if (! $companySetup || ! $companySetup->payroll_frequency_id) {
            $this->error('❌ Company payroll frequency not configured. Please set up payroll frequency first.');

            return Command::FAILURE;
        }

        // Determine generation strategy and date range
        if ($this->option('current-month')) {
            // Generate for current month only
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
            $this->info("📅 Generating PayPeriods for current month: {$startDate->format('M Y')}");

        } elseif ($this->option('weeks')) {
            // Generate N weeks ahead from current week
            $weeksAhead = (int) $this->option('weeks');
            $startDate = Carbon::now()->startOfWeek();
            $endDate = Carbon::now()->addWeeks($weeksAhead)->endOfWeek();
            $this->info("📅 Generating PayPeriods for next {$weeksAhead} weeks: {$startDate->format('M j')} - {$endDate->format('M j, Y')}");

        } else {
            // Generate N months ahead (default behavior)
            $monthsAhead = (int) $this->option('months');
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->addMonths($monthsAhead)->endOfMonth();
            $this->info("📅 Generating PayPeriods from {$startDate->format('M Y')} to {$endDate->format('M Y')}");
        }

        try {
            // Use the service which automatically prevents duplicates
            $createdPeriods = $generator->createAndSavePayPeriods($startDate, $endDate);

            if ($createdPeriods->count() > 0) {
                $this->info("✅ Created {$createdPeriods->count()} new PayPeriods");

                // Show summary
                $this->table(
                    ['Name', 'Start Date', 'End Date', 'Days'],
                    $createdPeriods->map(function ($period) {
                        $days = $period->start_date->diffInDays($period->end_date) + 1;

                        return [
                            $period->name ?? '-',
                            $period->start_date->format('M j, Y'),
                            $period->end_date->format('M j, Y'),
                            $days.' days',
                        ];
                    })->toArray()
                );
            } else {
                $this->info('ℹ️  No new PayPeriods needed - all periods already exist');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("❌ Error generating PayPeriods: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
