<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ClockEventProcessing\ClockEventProcessingService;

class ProcessClockEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clock-events:process
                            {--batch-size=100 : Number of events to process in one batch}
                            {--employee= : Process events for specific employee ID}
                            {--start-date= : Start date for processing (YYYY-MM-DD)}
                            {--end-date= : End date for processing (YYYY-MM-DD)}
                            {--retry-failed : Retry events that previously failed processing}
                            {--stats : Show processing statistics only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process ClockEvents into Attendance records';

    /**
     * The processing service
     */
    protected ClockEventProcessingService $processingService;

    /**
     * Create a new command instance.
     */
    public function __construct(ClockEventProcessingService $processingService)
    {
        parent::__construct();
        $this->processingService = $processingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🕐 ClockEvent Processing Started');
        $this->newLine();

        // Show stats only
        if ($this->option('stats')) {
            return $this->showStats();
        }

        // Retry failed events
        if ($this->option('retry-failed')) {
            return $this->retryFailedEvents();
        }

        // Process specific employee
        if ($this->option('employee')) {
            return $this->processEmployeeEvents();
        }

        // Regular batch processing
        return $this->processBatch();
    }

    /**
     * Show processing statistics
     */
    protected function showStats()
    {
        $stats = $this->processingService->getProcessingStats();

        $this->info('📊 ClockEvent Processing Statistics');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Events', number_format($stats['total_events'])],
                ['Processed Events', number_format($stats['processed_events'])],
                ['Unprocessed Events', number_format($stats['unprocessed_events'])],
                ['Events with Errors', number_format($stats['events_with_errors'])],
                ['Ready for Processing', number_format($stats['ready_for_processing'])],
            ]
        );

        // Show processing percentage
        if ($stats['total_events'] > 0) {
            $processedPercent = ($stats['processed_events'] / $stats['total_events']) * 100;
            $this->info("Processing Progress: " . number_format($processedPercent, 1) . "%");
        }

        return 0;
    }

    /**
     * Retry failed events
     */
    protected function retryFailedEvents()
    {
        $this->info('🔄 Retrying failed events...');

        $result = $this->processingService->retryFailedEvents();

        $this->info("✅ Cleared errors for {$result['cleared_errors']} events");
        $this->info("📋 {$result['ready_for_retry']} events ready for retry");

        if ($result['ready_for_retry'] > 0) {
            if ($this->confirm('Process the retry-ready events now?')) {
                return $this->processBatch();
            }
        }

        return 0;
    }

    /**
     * Process events for specific employee
     */
    protected function processEmployeeEvents()
    {
        $employeeId = $this->option('employee');
        $startDate = $this->option('start-date') ?? now()->subDays(7)->format('Y-m-d');
        $endDate = $this->option('end-date') ?? now()->format('Y-m-d');

        $this->info("👤 Processing events for Employee ID: {$employeeId}");
        $this->info("📅 Date range: {$startDate} to {$endDate}");

        $result = $this->processingService->processEmployeeEvents($employeeId, $startDate, $endDate);

        if (isset($result['message'])) {
            $this->warn($result['message']);
            return 0;
        }

        $this->displayResults($result);
        return 0;
    }

    /**
     * Process batch of unprocessed events
     */
    protected function processBatch()
    {
        $batchSize = (int) $this->option('batch-size');

        $this->info("⚡ Processing up to {$batchSize} unprocessed events...");

        // Show progress bar for larger batches
        $showProgress = $batchSize > 50;
        if ($showProgress) {
            $progressBar = $this->output->createProgressBar($batchSize);
            $progressBar->start();
        }

        $result = $this->processingService->processUnprocessedEvents($batchSize);

        if ($showProgress) {
            $progressBar->advance($result['processed'] + $result['errors']);
            $progressBar->finish();
            $this->newLine();
        }

        $this->displayResults($result);

        // Suggest next action if there are more events to process
        $stats = $this->processingService->getProcessingStats();
        if ($stats['ready_for_processing'] > 0) {
            $this->newLine();
            $this->info("💡 {$stats['ready_for_processing']} more events ready for processing");

            if ($this->confirm('Process another batch?')) {
                return $this->processBatch();
            }
        }

        return 0;
    }

    /**
     * Display processing results
     */
    protected function displayResults(array $result)
    {
        $this->newLine();
        $this->info('📈 Processing Results:');

        $this->table(
            ['Metric', 'Count'],
            [
                ['✅ Processed', $result['processed'] ?? 0],
                ['❌ Errors', $result['errors'] ?? 0],
                ['⏭️ Skipped', $result['skipped'] ?? 0],
            ]
        );

        if (isset($result['batch_id'])) {
            $this->info("🏷️ Batch ID: {$result['batch_id']}");
        }

        // Show warnings for errors
        if (($result['errors'] ?? 0) > 0) {
            $this->warn("⚠️ Some events failed to process. Check logs or run with --retry-failed");
        }

        // Show success message
        if (($result['processed'] ?? 0) > 0) {
            $this->info("✅ Successfully processed {$result['processed']} events into attendance records");
        }
    }
}