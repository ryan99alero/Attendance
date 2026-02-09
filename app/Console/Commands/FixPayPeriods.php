<?php

namespace App\Console\Commands;

use Illuminate\Support\Collection;
use Illuminate\Console\Command;
use App\Models\PayPeriod;
use App\Models\Punch;
use App\Services\Payroll\PayPeriodGeneratorService;
use Carbon\Carbon;

class FixPayPeriods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:fix-periods {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix overlapping PayPeriods and regenerate proper sequential weekly periods';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Analyzing current PayPeriods for overlaps and issues...');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Analyze current PayPeriods
        $this->analyzeCurrentPayPeriods();

        // Detect overlaps
        $overlappingPeriods = $this->detectOverlappingPeriods();

        if ($overlappingPeriods->count() > 0) {
            $this->error("Found {$overlappingPeriods->count()} overlapping PayPeriods!");

            if (!$isDryRun) {
                if ($this->confirm('Delete overlapping PayPeriods and regenerate proper sequential periods?')) {
                    $this->fixOverlappingPeriods($overlappingPeriods);
                    $this->regenerateProperPeriods();
                }
            } else {
                $this->info('Would delete overlapping periods and regenerate proper sequential periods');
            }
        } else {
            $this->info('✅ No overlapping PayPeriods found');
        }

        // Check for proper sequence
        $this->validateSequence();

        return 0;
    }

    private function analyzeCurrentPayPeriods(): void
    {
        $payPeriods = PayPeriod::orderBy('start_date')->get();

        $this->info("Current PayPeriods:");
        $this->table(
            ['ID', 'Start Date', 'End Date', 'Days', 'Processed', 'Posted', 'Punch Count'],
            $payPeriods->map(function ($period) {
                $daySpan = Carbon::parse($period->start_date)->diffInDays(Carbon::parse($period->end_date)) + 1;
                $punchCount = Punch::where('pay_period_id', $period->id)->count();

                return [
                    $period->id,
                    $period->start_date->format('M j, Y'),
                    $period->end_date->format('M j, Y'),
                    $daySpan,
                    $period->is_processed ? '✅' : '❌',
                    $period->is_posted ? '✅' : '❌',
                    $punchCount
                ];
            })->toArray()
        );
    }

    private function detectOverlappingPeriods(): Collection
    {
        $payPeriods = PayPeriod::orderBy('start_date')->get();
        $overlapping = collect();

        foreach ($payPeriods as $i => $period1) {
            foreach ($payPeriods as $j => $period2) {
                if ($i >= $j) continue; // Don't compare with self or already compared pairs

                $start1 = Carbon::parse($period1->start_date);
                $end1 = Carbon::parse($period1->end_date);
                $start2 = Carbon::parse($period2->start_date);
                $end2 = Carbon::parse($period2->end_date);

                // Check for overlap
                if ($start1->lte($end2) && $end1->gte($start2)) {
                    $this->error("OVERLAP: Period {$period1->id} ({$start1->format('M j')}-{$end1->format('M j')}) overlaps with Period {$period2->id} ({$start2->format('M j')}-{$end2->format('M j')})");
                    $overlapping->push($period1);
                    $overlapping->push($period2);
                }
            }
        }

        return $overlapping->unique('id');
    }

    private function fixOverlappingPeriods($overlappingPeriods): void
    {
        $this->info('Fixing overlapping PayPeriods...');

        foreach ($overlappingPeriods as $period) {
            // Check if this period has punch records
            $punchCount = Punch::where('pay_period_id', $period->id)->count();

            if ($punchCount > 0) {
                $this->warn("PayPeriod {$period->id} has {$punchCount} punch records - these will need to be reassigned");

                // Move punch records to a temporary holding area or reassign them
                Punch::where('pay_period_id', $period->id)->update(['pay_period_id' => null]);
                $this->info("Temporarily unassigned {$punchCount} punch records from PayPeriod {$period->id}");
            }

            $this->info("Deleting overlapping PayPeriod {$period->id}");
            $period->delete();
        }
    }

    private function regenerateProperPeriods(): void
    {
        $this->info('Regenerating proper sequential PayPeriods...');

        $generator = new PayPeriodGeneratorService();

        // Generate periods for a reasonable range (3 months back, 3 months forward)
        $startDate = Carbon::now()->subMonths(3)->startOfMonth();
        $endDate = Carbon::now()->addMonths(3)->endOfMonth();

        $newPeriods = $generator->createAndSavePayPeriods($startDate, $endDate);

        $this->info("Generated {$newPeriods->count()} new sequential PayPeriods");

        // Show the new periods
        if ($newPeriods->count() > 0) {
            $this->table(
                ['Start Date', 'End Date', 'Pay Date'],
                $newPeriods->map(function ($period) {
                    return [
                        $period->start_date->format('M j, Y'),
                        $period->end_date->format('M j, Y'),
                        $period->pay_date->format('M j, Y'),
                    ];
                })->toArray()
            );
        }

        // Reassign orphaned punch records to correct PayPeriods
        $this->reassignOrphanedPunches();
    }

    private function reassignOrphanedPunches(): void
    {
        $orphanedPunches = Punch::whereNull('pay_period_id')->get();

        if ($orphanedPunches->count() > 0) {
            $this->info("Reassigning {$orphanedPunches->count()} orphaned punch records...");

            $reassigned = 0;
            foreach ($orphanedPunches as $punch) {
                $punchDate = Carbon::parse($punch->punch_time);

                // Find the PayPeriod this punch belongs to
                $correctPeriod = PayPeriod::where('start_date', '<=', $punchDate)
                    ->where('end_date', '>=', $punchDate)
                    ->first();

                if ($correctPeriod) {
                    $punch->update(['pay_period_id' => $correctPeriod->id]);
                    $reassigned++;
                }
            }

            $this->info("Successfully reassigned {$reassigned} punch records");

            $stillOrphaned = $orphanedPunches->count() - $reassigned;
            if ($stillOrphaned > 0) {
                $this->warn("{$stillOrphaned} punch records could not be reassigned (dates outside PayPeriod range)");
            }
        }
    }

    private function validateSequence(): void
    {
        $this->info('Validating PayPeriod sequence...');

        $payPeriods = PayPeriod::orderBy('start_date')->get();
        $previousEnd = null;
        $isValid = true;

        foreach ($payPeriods as $period) {
            if ($previousEnd) {
                $gap = $previousEnd->diffInDays(Carbon::parse($period->start_date));
                if ($gap !== 1) {
                    $this->error("Invalid sequence: Gap of {$gap} days between {$previousEnd->format('M j')} and {$period->start_date->format('M j')}");
                    $isValid = false;
                }
            }
            $previousEnd = Carbon::parse($period->end_date);
        }

        if ($isValid) {
            $this->info('✅ PayPeriod sequence is valid - all periods are sequential');
        }
    }
}
