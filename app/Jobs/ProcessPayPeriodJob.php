<?php

namespace App\Jobs;

use App\Models\PayPeriod;
use App\Models\SystemLog;
use App\Models\SystemTask;
use App\Models\User;
use App\Services\AttendanceProcessing\AttendanceProcessingService;
use App\Traits\TracksSystemTask;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessPayPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksSystemTask;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public PayPeriod $payPeriod,
        public ?int $userId = null
    ) {}

    public function handle(AttendanceProcessingService $processingService): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        // Create system task for tracking
        $this->initializeSystemTask(
            type: SystemTask::TYPE_PROCESSING,
            name: "Pay Period: {$this->payPeriod->name}",
            description: "Processing attendance for {$this->payPeriod->start_date->format('M j')} - {$this->payPeriod->end_date->format('M j, Y')}",
            relatedModel: PayPeriod::class,
            relatedId: $this->payPeriod->id,
            userId: $this->userId
        );

        $log = SystemLog::logEvent(
            type: 'pay_period_processing',
            summary: "Processing Pay Period: {$this->payPeriod->name}",
            level: SystemLog::LEVEL_INFO,
            metadata: ['pay_period_id' => $this->payPeriod->id],
            context: $this->payPeriod
        );
        $log->update(['status' => SystemLog::STATUS_RUNNING, 'started_at' => now(), 'user_id' => $this->userId]);

        // Initialize progress tracking
        $this->payPeriod->update([
            'processing_status' => 'processing',
            'processing_progress' => 5,
            'processing_message' => 'Initializing...',
            'processing_started_at' => now(),
            'processing_error' => null,
        ]);

        try {
            $this->updateTaskProgress(5, 'Initializing...');

            // The processing service now updates progress internally
            $processingService->processAll($this->payPeriod);

            // Complete
            $this->payPeriod->update([
                'processing_status' => 'completed',
                'processing_progress' => 100,
                'processing_message' => 'Processing complete',
                'processing_completed_at' => now(),
            ]);

            $log->markSuccess();
            $this->completeTask('Processing complete');

            // Notify user
            $this->notifyUser(true);

        } catch (Throwable $e) {
            $this->payPeriod->update([
                'processing_status' => 'failed',
                'processing_message' => 'Processing failed',
                'processing_error' => $e->getMessage(),
                'processing_completed_at' => now(),
            ]);

            $log->markFailed($e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->failTask($e->getMessage());

            // Notify user of failure
            $this->notifyUser(false, $e->getMessage());

            throw $e;
        }
    }

    protected function notifyUser(bool $success, ?string $errorMessage = null): void
    {
        if (! $this->userId) {
            return;
        }

        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        if ($success) {
            Notification::make()
                ->success()
                ->title('Pay Period Processing Complete')
                ->body("Pay Period '{$this->payPeriod->name}' has been processed successfully.")
                ->sendToDatabase($user);
        } else {
            Notification::make()
                ->danger()
                ->title('Pay Period Processing Failed')
                ->body("Pay Period '{$this->payPeriod->name}' failed: ".($errorMessage ?? 'Unknown error'))
                ->sendToDatabase($user);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->payPeriod->update([
            'processing_status' => 'failed',
            'processing_message' => 'Processing failed',
            'processing_error' => $exception->getMessage(),
            'processing_completed_at' => now(),
        ]);

        $this->failTask($exception->getMessage());
        $this->notifyUser(false, $exception->getMessage());
    }
}
