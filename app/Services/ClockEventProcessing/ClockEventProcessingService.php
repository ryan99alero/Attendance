<?php

namespace App\Services\ClockEventProcessing;

use App\Models\Attendance;
use App\Models\ClockEvent;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClockEventProcessingService
{
    /**
     * Process unprocessed ClockEvents into Attendance records
     */
    public function processUnprocessedEvents(int $batchSize = 100): array
    {
        $batchId = Str::uuid();
        $stats = [
            'processed' => 0,
            'errors' => 0,
            'skipped' => 0,
            'batch_id' => $batchId,
        ];

        Log::info('[ClockEventProcessing] Starting batch processing', [
            'batch_id' => $batchId,
            'batch_size' => $batchSize,
        ]);

        // Get IDs only first to minimize memory
        $eventIds = ClockEvent::readyForProcessing()
            ->orderBy('event_time')
            ->limit($batchSize)
            ->pluck('id')
            ->toArray();

        if (empty($eventIds)) {
            Log::info('[ClockEventProcessing] No unprocessed events found');

            return $stats;
        }

        // Process each event individually to avoid memory issues
        foreach ($eventIds as $eventId) {
            try {
                // Load fresh each time
                $event = ClockEvent::with('employee')->find($eventId);

                if (! $event) {
                    continue;
                }

                if (! $event->employee) {
                    $this->markEventAsError($event, 'No employee assigned', $batchId);
                    $stats['errors']++;

                    continue;
                }

                $this->processSingleEvent($event, $batchId, $stats);

            } catch (Exception $e) {
                Log::error('[ClockEventProcessing] Failed to process event', [
                    'event_id' => $eventId,
                    'batch_id' => $batchId,
                    'error' => $e->getMessage(),
                ]);

                // Try to mark the event as error
                $event = ClockEvent::find($eventId);
                if ($event) {
                    $this->markEventAsError($event, $e->getMessage(), $batchId);
                }
                $stats['errors']++;
            }
        }

        Log::info('[ClockEventProcessing] Batch processing completed', [
            'batch_id' => $batchId,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Process a single clock event
     */
    protected function processSingleEvent(ClockEvent $event, string $batchId, array &$stats): void
    {
        $clockEventId = $event->id;

        Log::debug('[ClockEventProcessing] Processing event', [
            'clock_event_id' => $clockEventId,
            'employee_id' => $event->employee_id,
            'shift_date' => $event->shift_date?->format('Y-m-d'),
            'batch_id' => $batchId,
        ]);

        $attendance = $this->createAttendanceRecord($event, $batchId);

        // Delete clock event after successful processing
        $event->delete();

        $stats['processed']++;

        Log::debug('[ClockEventProcessing] Created attendance record', [
            'clock_event_id' => $clockEventId,
            'attendance_id' => $attendance->id,
            'batch_id' => $batchId,
        ]);
    }

    /**
     * Create an attendance record from a clock event
     */
    protected function createAttendanceRecord(ClockEvent $event, string $batchId): Attendance
    {
        return Attendance::create([
            'employee_id' => $event->employee_id,
            'device_id' => $event->device_id,
            'punch_time' => $event->event_time,
            'punch_type_id' => null, // Let processing engines assign punch types
            'punch_state' => 'unknown', // Let processing engines determine state
            'shift_date' => $event->shift_date,
            'status' => 'Incomplete', // Mark as incomplete so processing engines will handle it
            'is_manual' => false,
            'source' => 'device',
            'is_migrated' => false, // Will be processed by normal attendance workflow
            'issue_notes' => "Auto-generated from ClockEvent ID: {$event->id}",
            'created_by' => null, // System generated - no specific user
        ]);
    }

    /**
     * Mark an event as having a processing error
     */
    protected function markEventAsError(ClockEvent $event, string $error, string $batchId): void
    {
        $event->update([
            'processing_error' => $error,
            'batch_id' => $batchId,
        ]);
    }

    /**
     * Get processing statistics
     *
     * Note: Processed events are deleted from clock_events table.
     * Only pending and errored events remain in this table.
     * Successfully processed events live in the attendances table.
     */
    public function getProcessingStats(): array
    {
        $pendingCount = ClockEvent::readyForProcessing()->count();
        $errorCount = ClockEvent::withErrors()->count();
        $totalInTable = ClockEvent::count();

        // Count of successfully processed = records in attendance from clock events
        $processedCount = Attendance::whereNotNull('issue_notes')
            ->where('issue_notes', 'like', 'Auto-generated from ClockEvent ID:%')
            ->count();

        return [
            'total_events' => $totalInTable + $processedCount, // Total ever received
            'processed_events' => $processedCount, // Now in attendance table
            'unprocessed_events' => $totalInTable, // Still in clock_events
            'events_with_errors' => $errorCount,
            'ready_for_processing' => $pendingCount,
        ];
    }

    /**
     * Retry failed events (clear errors and reprocess)
     */
    public function retryFailedEvents(): array
    {
        $clearedCount = ClockEvent::withErrors()
            ->update([
                'processing_error' => null,
                'batch_id' => null,
            ]);

        return [
            'cleared_errors' => $clearedCount,
            'ready_for_retry' => ClockEvent::readyForProcessing()->count(),
        ];
    }

    /**
     * Process events for a specific employee and date range
     */
    public function processEmployeeEvents(int $employeeId, string $startDate, string $endDate): array
    {
        $eventIds = ClockEvent::where('employee_id', $employeeId)
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->orderBy('event_time')
            ->pluck('id')
            ->toArray();

        if (empty($eventIds)) {
            return ['processed' => 0, 'message' => 'No unprocessed events found for this employee/date range'];
        }

        $batchId = Str::uuid();
        $stats = ['processed' => 0, 'errors' => 0, 'batch_id' => $batchId];

        foreach ($eventIds as $eventId) {
            try {
                $event = ClockEvent::with('employee')->find($eventId);
                if ($event && $event->employee) {
                    $this->processSingleEvent($event, $batchId, $stats);
                }
            } catch (Exception $e) {
                Log::error('[ClockEventProcessing] Failed to process employee event', [
                    'employee_id' => $employeeId,
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        return $stats;
    }
}
