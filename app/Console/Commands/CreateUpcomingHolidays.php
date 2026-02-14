<?php

namespace App\Console\Commands;

use App\Models\HolidayInstance;
use App\Models\HolidayTemplate;
use Exception;
use Illuminate\Console\Command;

class CreateUpcomingHolidays extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'holidays:create-upcoming
                           {--year= : Create holidays for specific year (default: current year)}
                           {--template= : Process specific holiday template ID}
                           {--dry-run : Show what would be created without making changes}
                           {--force : Recreate holidays even if they already exist}';

    /**
     * The console command description.
     */
    protected $description = 'Create upcoming holiday instances based on active holiday templates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : now()->year;
        $templateId = $this->option('template');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🎉 Creating holiday instances for year: {$year}");

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }

        // Get holiday templates to process
        $templates = $this->getTemplatesToProcess($templateId);

        if ($templates->isEmpty()) {
            $this->info('✅ No active holiday templates found to process');

            return 0;
        }

        $this->info('📋 Found '.$templates->count().' holiday template(s) to process');

        $created = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($templates->count());
        $progressBar->start();

        foreach ($templates as $template) {
            try {
                $result = $this->processHolidayTemplate($template, $year, $force, $dryRun);

                if ($result['created']) {
                    $created++;
                    $this->newLine();
                    $this->info("✅ {$template->name}: Created holiday instance for {$result['date']}");
                } else {
                    $skipped++;
                    if ($this->output->isVerbose()) {
                        $this->newLine();
                        $this->comment("⏭️  {$template->name}: {$result['reason']}");
                    }
                }

            } catch (Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("❌ {$template->name}: ".$e->getMessage());
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
                ['✅ Created', $created],
                ['⏭️  Skipped', $skipped],
                ['❌ Errors', $errors],
                ['📋 Total Templates', $templates->count()],
            ]
        );

        if ($dryRun && $created > 0) {
            $this->warn('🧪 This was a dry run. Run without --dry-run to actually create holiday instances.');
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
     * Process a single holiday template - creates ONE HolidayInstance record per template/year
     */
    protected function processHolidayTemplate(HolidayTemplate $template, int $year, bool $force, bool $dryRun): array
    {
        // Calculate holiday date for the year
        $holidayDate = $template->calculateDateForYear($year);

        // Check if holiday instance already exists for this template and year
        $existing = HolidayInstance::where('holiday_template_id', $template->id)
            ->where('year', $year)
            ->first();

        if ($existing && ! $force) {
            return [
                'created' => false,
                'reason' => "Already exists for {$holidayDate->toDateString()}",
                'date' => $holidayDate->toDateString(),
            ];
        }

        if ($dryRun) {
            return [
                'created' => true,
                'reason' => 'Would create holiday instance',
                'date' => $holidayDate->toDateString(),
            ];
        }

        // Delete existing instance if force is enabled
        if ($existing && $force) {
            $existing->delete();
        }

        // Create the holiday instance (ONE record, not one per employee)
        HolidayInstance::create([
            'holiday_template_id' => $template->id,
            'holiday_date' => $holidayDate,
            'year' => $year,
            'name' => $template->name,
            'holiday_multiplier' => $template->holiday_multiplier ?? 2.00,
            'standard_hours' => $template->standard_holiday_hours ?? 8.00,
            'require_day_before' => $template->require_day_before ?? false,
            'require_day_after' => $template->require_day_after ?? false,
            'paid_if_not_worked' => $template->paid_if_not_worked ?? true,
            'eligible_pay_types' => $template->eligible_pay_types,
            'is_active' => true,
        ]);

        return [
            'created' => true,
            'reason' => 'Successfully created',
            'date' => $holidayDate->toDateString(),
        ];
    }
}
