<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\ClockEvent;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessClockEventsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800; // 30 minutes

    public function __construct(
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        // Disable query logging - this is the main memory culprit
        DB::disableQueryLog();

        $batchId = (string) Str::uuid();
        $processed = 0;
        $errors = 0;

        Log::info('[ProcessClockEventsJob] Starting', [
            'batch_id' => $batchId,
            'pending_count' => ClockEvent::readyForProcessing()->count(),
        ]);

        // Use chunkById to process efficiently - Laravel handles memory management
        ClockEvent::readyForProcessing()
            ->with('employee') // Eager load to prevent N+1
            ->chunkById(100, function ($events) use ($batchId, &$processed, &$errors) {
                foreach ($events as $event) {
                    try {
                        if (! $event->employee) {
                            $event->update([
                                'processing_error' => 'No employee assigned',
                                'batch_id' => $batchId,
                            ]);
                            $errors++;

                            continue;
                        }

                        // Create attendance record
                        Attendance::create([
                            'employee_id' => $event->employee_id,
                            'device_id' => $event->device_id,
                            'punch_time' => $event->event_time,
                            'punch_type_id' => null,
                            'punch_state' => 'unknown',
                            'shift_date' => $event->shift_date,
                            'status' => 'Incomplete',
                            'is_manual' => false,
                            'source' => 'device',
                            'is_migrated' => false,
                            'issue_notes' => "Auto-generated from ClockEvent ID: {$event->id}",
                            'created_by' => null,
                        ]);

                        // Delete the clock event
                        $event->delete();
                        $processed++;

                    } catch (\Throwable $e) {
                        Log::error('[ProcessClockEventsJob] Event failed', [
                            'event_id' => $event->id,
                            'error' => $e->getMessage(),
                        ]);

                        $event->update([
                            'processing_error' => substr($e->getMessage(), 0, 500),
                            'batch_id' => $batchId,
                        ]);
                        $errors++;
                    }
                }

                // Log progress every chunk
                Log::info('[ProcessClockEventsJob] Chunk complete', [
                    'processed' => $processed,
                    'errors' => $errors,
                ]);
            });

        Log::info('[ProcessClockEventsJob] Complete', [
            'total_processed' => $processed,
            'total_errors' => $errors,
        ]);

        // Send notification
        if ($this->userId) {
            $user = User::find($this->userId);
            if ($user) {
                $body = "Processed {$processed} clock events into attendance records.";
                if ($errors > 0) {
                    $body .= " {$errors} events had errors.";
                }

                Notification::make()
                    ->title('Clock Event Processing Complete')
                    ->body($body)
                    ->success()
                    ->sendToDatabase($user);
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('[ProcessClockEventsJob] Job failed', [
            'error' => $exception?->getMessage(),
        ]);

        if ($this->userId) {
            $user = User::find($this->userId);
            if ($user) {
                Notification::make()
                    ->title('Clock Event Processing Failed')
                    ->body('An error occurred. Check logs for details.')
                    ->danger()
                    ->sendToDatabase($user);
            }
        }
    }
}
