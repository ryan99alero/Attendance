<?php

namespace App\Services\ClockEventProcessing;

use App\Models\ClockEvent;
use App\Models\Attendance;
use App\Models\PunchType;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

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
            'batch_id' => $batchId
        ];

        Log::info("[ClockEventProcessing] Starting batch processing", [
            'batch_id' => $batchId,
            'batch_size' => $batchSize
        ]);

        // Get unprocessed events in batches
        $unprocessedEvents = ClockEvent::readyForProcessing()
            ->orderBy('event_time')
            ->limit($batchSize)
            ->get();

        if ($unprocessedEvents->isEmpty()) {
            Log::info("[ClockEventProcessing] No unprocessed events found");
            return $stats;
        }

        // Group events by employee and shift date for processing
        $groupedEvents = $unprocessedEvents->groupBy(function ($event) {
            return $event->employee_id . '_' . $event->shift_date->format('Y-m-d');
        });

        foreach ($groupedEvents as $groupKey => $events) {
            try {
                $this->processEventGroup($events, $batchId, $stats);
            } catch (\Exception $e) {
                Log::error("[ClockEventProcessing] Failed to process group", [
                    'group_key' => $groupKey,
                    'batch_id' => $batchId,
                    'error' => $e->getMessage()
                ]);

                // Mark all events in this group as having errors
                foreach ($events as $event) {
                    $this->markEventAsError($event, $e->getMessage(), $batchId);
                    $stats['errors']++;
                }
            }
        }

        Log::info("[ClockEventProcessing] Batch processing completed", [
            'batch_id' => $batchId,
            'stats' => $stats
        ]);

        return $stats;
    }

    /**
     * Process a group of events for the same employee/day
     */
    protected function processEventGroup($events, string $batchId, array &$stats): void
    {
        $employee = $events->first()->employee;
        $shiftDate = $events->first()->shift_date;

        Log::info("[ClockEventProcessing] Processing event group", [
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_names,
            'shift_date' => $shiftDate->format('Y-m-d'),
            'event_count' => $events->count(),
            'batch_id' => $batchId
        ]);

        // Sort events by time
        $sortedEvents = $events->sortBy('event_time');

        // Create attendance records for each event (no punch type assignment)
        foreach ($sortedEvents as $event) {
            try {
                $attendance = $this->createAttendanceRecord($event, $batchId);

                // Mark event as processed
                $event->update([
                    'is_processed' => true,
                    'processed_at' => now(),
                    'attendance_id' => $attendance->id,
                    'batch_id' => $batchId,
                    'processing_error' => null
                ]);

                $stats['processed']++;

                Log::info("[ClockEventProcessing] Created attendance record", [
                    'clock_event_id' => $event->id,
                    'attendance_id' => $attendance->id,
                    'status' => 'Incomplete - ready for processing engines',
                    'batch_id' => $batchId
                ]);

            } catch (\Exception $e) {
                $this->markEventAsError($event, $e->getMessage(), $batchId);
                $stats['errors']++;

                Log::error("[ClockEventProcessing] Failed to create attendance record", [
                    'clock_event_id' => $event->id,
                    'error' => $e->getMessage(),
                    'batch_id' => $batchId
                ]);
            }
        }
    }


    /**
     * Create an attendance record from a clock event
     */
    protected function createAttendanceRecord($event, string $batchId): Attendance
    {
        $attendance = Attendance::create([
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

        return $attendance;
    }

    /**
     * Mark an event as having a processing error
     */
    protected function markEventAsError(ClockEvent $event, string $error, string $batchId): void
    {
        $event->update([
            'processing_error' => $error,
            'batch_id' => $batchId,
            'is_processed' => false // Keep as unprocessed for retry
        ]);
    }

    /**
     * Get processing statistics
     */
    public function getProcessingStats(): array
    {
        return [
            'total_events' => ClockEvent::count(),
            'processed_events' => ClockEvent::processed()->count(),
            'unprocessed_events' => ClockEvent::unprocessed()->count(),
            'events_with_errors' => ClockEvent::withErrors()->count(),
            'ready_for_processing' => ClockEvent::readyForProcessing()->count(),
        ];
    }

    /**
     * Retry failed events (clear errors and reprocess)
     */
    public function retryFailedEvents(): array
    {
        $failedEvents = ClockEvent::withErrors()->get();

        foreach ($failedEvents as $event) {
            $event->update([
                'processing_error' => null,
                'batch_id' => null
            ]);
        }

        return [
            'cleared_errors' => $failedEvents->count(),
            'ready_for_retry' => ClockEvent::readyForProcessing()->count()
        ];
    }

    /**
     * Process events for a specific employee and date range
     */
    public function processEmployeeEvents(int $employeeId, string $startDate, string $endDate): array
    {
        $events = ClockEvent::unprocessed()
            ->where('employee_id', $employeeId)
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->orderBy('event_time')
            ->get();

        if ($events->isEmpty()) {
            return ['processed' => 0, 'message' => 'No unprocessed events found for this employee/date range'];
        }

        $batchId = Str::uuid();
        $stats = ['processed' => 0, 'errors' => 0, 'batch_id' => $batchId];

        $groupedEvents = $events->groupBy(function ($event) {
            return $event->shift_date->format('Y-m-d');
        });

        foreach ($groupedEvents as $date => $dayEvents) {
            try {
                $this->processEventGroup($dayEvents, $batchId, $stats);
            } catch (\Exception $e) {
                Log::error("[ClockEventProcessing] Failed to process employee events", [
                    'employee_id' => $employeeId,
                    'date' => $date,
                    'error' => $e->getMessage()
                ]);
                $stats['errors'] += $dayEvents->count();
            }
        }

        return $stats;
    }
}