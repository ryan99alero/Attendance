<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceProcessing\AttendanceClassificationService;
use App\Models\Attendance;
use App\Models\Classification;

class ClassifyAttendanceRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:classify {--dry-run : Show what would be classified without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classify all unclassified attendance records automatically';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceClassificationService $classificationService)
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Starting attendance record classification');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Show current statistics
        $totalAttendance = Attendance::count();
        $unclassified = Attendance::whereNull('classification_id')->count();
        $classified = $totalAttendance - $unclassified;

        $this->info("Current status:");
        $this->info("  Total attendance records: {$totalAttendance}");
        $this->info("  Classified: {$classified}");
        $this->info("  Unclassified: {$unclassified}");

        if ($unclassified === 0) {
            $this->info('All attendance records are already classified!');
            return 0;
        }

        // Show available classifications
        $this->info("\nAvailable classifications:");
        $classifications = Classification::orderBy('id')->get();
        foreach ($classifications as $classification) {
            $this->line("  {$classification->id}: {$classification->name} ({$classification->code})");
        }

        if ($isDryRun) {
            $this->info("\nDry run - analyzing classification patterns...");
            $this->analyzeClassificationPatterns();
        } else {
            $this->info("\nClassifying attendance records...");
            $classificationService->classifyAllUnclassifiedAttendance();

            // Show results
            $newUnclassified = Attendance::whereNull('classification_id')->count();
            $newClassified = $totalAttendance - $newUnclassified;
            $processed = $classified !== $newClassified ? $newClassified - $classified : 0;

            $this->info("\nResults:");
            $this->info("  Newly classified: {$processed}");
            $this->info("  Total classified: {$newClassified}");
            $this->info("  Still unclassified: {$newUnclassified}");

            if ($newUnclassified > 0) {
                $this->warn("Some records could not be automatically classified and may need manual review.");
            }
        }

        return 0;
    }

    private function analyzeClassificationPatterns(): void
    {
        $patterns = [
            'vacation' => Attendance::whereNull('classification_id')
                ->where('issue_notes', 'like', '%vacation%')
                ->count(),
            'holiday' => Attendance::whereNull('classification_id')
                ->where(function($query) {
                    $query->where('issue_notes', 'like', '%holiday%')
                          ->orWhereNotNull('holiday_id');
                })
                ->count(),
            'sick' => Attendance::whereNull('classification_id')
                ->where('issue_notes', 'like', '%sick%')
                ->count(),
            'training' => Attendance::whereNull('classification_id')
                ->where('issue_notes', 'like', '%training%')
                ->count(),
            'remote' => Attendance::whereNull('classification_id')
                ->where(function($query) {
                    $query->where('issue_notes', 'like', '%remote%')
                          ->orWhere('issue_notes', 'like', '%home%');
                })
                ->count(),
            'regular_potential' => Attendance::whereNull('classification_id')
                ->whereHas('punchType', function($query) {
                    $query->whereIn('name', ['Clock In', 'Clock Out', 'Lunch Start', 'Lunch Stop', 'Break Start', 'Break End']);
                })
                ->count(),
        ];

        $this->info("\nClassification pattern analysis:");
        foreach ($patterns as $type => $count) {
            if ($count > 0) {
                $this->line("  {$type}: {$count} records");
            }
        }
    }
}
