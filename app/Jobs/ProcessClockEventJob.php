<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\ClockEvent;
use App\Models\CompanySetup;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessClockEventJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job may run.
     */
    public $timeout = 60;

    /**
     * The ClockEvent to process
     */
    protected ClockEvent $clockEvent;

    /**
     * Create a new job instance.
     */
    public function __construct(ClockEvent $clockEvent)
    {
        $this->clockEvent = $clockEvent;

        // Set queue based on company settings
        $companySetup = CompanySetup::first();
        if ($companySetup && $companySetup->clock_event_sync_frequency === 'real_time') {
            $this->onQueue('high'); // High priority queue for real-time
        } else {
            $this->onQueue('default');
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if event still exists (may have been processed by another job)
        if (! $this->clockEvent->exists) {
            Log::info('[QueueClockEventProcessing] Event no longer exists, skipping', [
                'clock_event_id' => $this->clockEvent->id,
            ]);

            return;
        }

        // Check if employee exists (should have been validated at creation)
        if (! $this->clockEvent->employee_id) {
            $this->markEventAsError('No employee associated with clock event');

            return;
        }

        $batchId = Str::uuid();

        try {
            Log::info('[QueueClockEventProcessing] Processing clock event', [
                'clock_event_id' => $this->clockEvent->id,
                'employee_id' => $this->clockEvent->employee_id,
                'event_time' => $this->clockEvent->event_time,
                'batch_id' => $batchId,
            ]);

            // Create attendance record
            $attendance = Attendance::create([
                'employee_id' => $this->clockEvent->employee_id,
                'device_id' => $this->clockEvent->device_id,
                'punch_time' => $this->clockEvent->event_time,
                'punch_type_id' => null, // Let processing engines assign punch types
                'punch_state' => 'unknown', // Let processing engines determine state
                'shift_date' => $this->clockEvent->shift_date,
                'status' => 'Incomplete', // Mark as incomplete so processing engines will handle it
                'is_manual' => false,
                'is_migrated' => false, // Will be processed by normal attendance workflow
                'issue_notes' => "Auto-generated from ClockEvent ID: {$this->clockEvent->id}",
                'created_by' => null, // System generated - no specific user
            ]);

            Log::info('[QueueClockEventProcessing] Successfully processed clock event', [
                'clock_event_id' => $this->clockEvent->id,
                'attendance_id' => $attendance->id,
                'employee_id' => $this->clockEvent->employee_id,
                'batch_id' => $batchId,
            ]);

            // Delete clock event after successful processing
            // Record now lives in attendance table - no need to keep duplicate
            $this->clockEvent->delete();

        } catch (Exception $e) {
            $this->markEventAsError($e->getMessage());

            Log::error('[QueueClockEventProcessing] Failed to process clock event', [
                'clock_event_id' => $this->clockEvent->id,
                'employee_id' => $this->clockEvent->employee_id,
                'error' => $e->getMessage(),
                'batch_id' => $batchId,
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('[QueueClockEventProcessing] Job failed after all retries', [
            'clock_event_id' => $this->clockEvent->id,
            'employee_id' => $this->clockEvent->employee_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->markEventAsError("Job failed after {$this->tries} attempts: ".$exception->getMessage());
    }

    /**
     * Mark the clock event as having an error
     */
    protected function markEventAsError(string $error): void
    {
        $this->clockEvent->update([
            'processing_error' => $error,
        ]);
    }
}
