<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HolidayTemplate;
use App\Models\VacationCalendar;
use App\Models\Employee;
use Carbon\Carbon;

class CreateUpcomingHolidays extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'holidays:create-upcoming
                           {--year= : Create holidays for specific year (default: next year)}
                           {--template= : Process specific holiday template ID}
                           {--dry-run : Show what would be created without making changes}
                           {--force : Recreate holidays even if they already exist}';

    /**
     * The console command description.
     */
    protected $description = 'Create upcoming holidays based on active holiday templates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->option('year') ? (int) $this->option('year') : Carbon::now()->addYear()->year;
        $templateId = $this->option('template');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🎉 Creating holidays for year: {$year}");

        if ($dryRun) {
            $this->warn("🧪 DRY RUN MODE - No changes will be made");
        }

        // Get holiday templates to process
        $templates = $this->getTemplatesToProcess($templateId);

        if ($templates->isEmpty()) {
            $this->info("✅ No active holiday templates found to process");
            return 0;
        }

        $this->info("📋 Found " . $templates->count() . " holiday template(s) to process");

        $created = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($templates->count());
        $progressBar->start();

        foreach ($templates as $template) {
            try {
                $result = $this->processHolidayTemplate($template, $year, $force, $dryRun);

                if ($result['created']) {
                    $created += $result['count'];
                    $this->newLine();
                    $this->info("✅ {$template->name}: Created {$result['count']} holiday(s) for {$result['date']}");
                } else {
                    $skipped++;
                    if ($this->output->isVerbose()) {
                        $this->newLine();
                        $this->comment("⏭️  {$template->name}: {$result['reason']}");
                    }
                }

            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("❌ {$template->name}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("📊 Processing Summary:");
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Created', $created],
                ['⏭️  Skipped', $skipped],
                ['❌ Errors', $errors],
                ['📋 Total Templates', $templates->count()],
            ]
        );

        if ($dryRun && $created > 0) {
            $this->warn("🧪 This was a dry run. Run without --dry-run to actually create holidays.");
        }

        return $errors > 0 ? 1 : 0;
    }

    /**
     * Get holiday templates to process
     */
    protected function getTemplatesToProcess(?int $templateId)
    {
        $query = HolidayTemplate::active();

        if ($templateId) {
            $query->where('id', $templateId);
        }

        return $query->get();
    }

    /**
     * Process a single holiday template
     */
    protected function processHolidayTemplate(HolidayTemplate $template, int $year, bool $force, bool $dryRun): array
    {
        // Calculate holiday date for the year
        $holidayDate = $template->calculateDateForYear($year);

        // Check if holidays already exist for this template and date
        if (!$force) {
            $existingCount = VacationCalendar::where('holiday_template_id', $template->id)
                ->whereDate('vacation_date', $holidayDate)
                ->count();

            if ($existingCount > 0) {
                return [
                    'created' => false,
                    'count' => 0,
                    'reason' => "Already exists for {$holidayDate->toDateString()} ({$existingCount} employees)",
                    'date' => $holidayDate->toDateString(),
                ];
            }
        }

        if ($dryRun) {
            $employeeCount = $this->getEligibleEmployees($template)->count();
            return [
                'created' => true,
                'count' => $employeeCount,
                'reason' => 'Would create holidays',
                'date' => $holidayDate->toDateString(),
            ];
        }

        // Get eligible employees
        $employees = $this->getEligibleEmployees($template);

        if ($employees->isEmpty()) {
            return [
                'created' => false,
                'count' => 0,
                'reason' => 'No eligible employees found',
                'date' => $holidayDate->toDateString(),
            ];
        }

        // Delete existing holidays if force is enabled
        if ($force) {
            VacationCalendar::where('holiday_template_id', $template->id)
                ->whereDate('vacation_date', $holidayDate)
                ->delete();
        }

        // Create vacation calendar entries for all eligible employees
        $created = 0;
        foreach ($employees as $employee) {
            VacationCalendar::create([
                'employee_id' => $employee->id,
                'vacation_date' => $holidayDate,
                'holiday_template_id' => $template->id,
                'holiday_type' => 'auto_created',
                'auto_managed' => true,
                'description' => $template->name,
                'is_half_day' => false,
                'is_active' => true,
                'created_by' => null, // System created
            ]);
            $created++;
        }

        return [
            'created' => true,
            'count' => $created,
            'reason' => 'Successfully created',
            'date' => $holidayDate->toDateString(),
        ];
    }

    /**
     * Get employees eligible for this holiday template
     */
    protected function getEligibleEmployees(HolidayTemplate $template)
    {
        $query = Employee::where('is_active', true);

        // Apply pay type eligibility filtering
        $eligiblePayTypes = $template->eligible_pay_types ?? ['salary', 'hourly_fulltime'];

        $query->where(function ($payTypeQuery) use ($eligiblePayTypes) {
            if (in_array('salary', $eligiblePayTypes)) {
                $payTypeQuery->orWhere('pay_type', 'salary');
            }

            if (in_array('hourly_fulltime', $eligiblePayTypes)) {
                $payTypeQuery->orWhere(function ($hourlyQuery) {
                    $hourlyQuery->where('pay_type', 'hourly')
                               ->where('full_time', true);
                });
            }

            if (in_array('hourly_parttime', $eligiblePayTypes)) {
                $payTypeQuery->orWhere(function ($hourlyQuery) {
                    $hourlyQuery->where('pay_type', 'hourly')
                               ->where('full_time', false);
                });
            }

            if (in_array('contract', $eligiblePayTypes)) {
                $payTypeQuery->orWhere('pay_type', 'contract');
            }
        });

        return $query->get();
    }
}