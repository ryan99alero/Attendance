<?php

namespace App\Observers;

use App\Jobs\RecalculateEmployeeSummaryJob;
use App\Models\Attendance;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;

/**
 * Observes Attendance model changes and triggers payroll summary recalculations.
 *
 * When attendance records are created, updated, or deleted, this observer
 * dispatches a job to recalculate the affected employee's payroll summaries.
 */
class AttendanceObserver
{
    /**
     * Handle the Attendance "created" event.
     */
    public function created(Attendance $attendance): void
    {
        $this->dispatchRecalculation($attendance);
    }

    /**
     * Handle the Attendance "updated" event.
     */
    public function updated(Attendance $attendance): void
    {
        // Check if relevant fields changed that would affect payroll calculations
        $relevantChanges = $attendance->wasChanged([
            'punch_time',
            'punch_type_id',
            'punch_state',
            'classification_id',
            'employee_id',
            'status',
        ]);

        if ($relevantChanges) {
            $this->dispatchRecalculation($attendance);

            // If employee_id changed, also recalculate for the old employee
            if ($attendance->wasChanged('employee_id')) {
                $oldEmployeeId = $attendance->getOriginal('employee_id');
                if ($oldEmployeeId) {
                    $this->dispatchRecalculationForEmployee($oldEmployeeId, $attendance->punch_time);
                }
            }

            // If punch_time changed significantly (different pay period), recalculate old period too
            if ($attendance->wasChanged('punch_time')) {
                $oldPunchTime = $attendance->getOriginal('punch_time');
                if ($oldPunchTime) {
                    $oldPayPeriod = $this->findPayPeriodForDate($oldPunchTime);
                    $newPayPeriod = $this->findPayPeriodForDate($attendance->punch_time);

                    if ($oldPayPeriod && $newPayPeriod && $oldPayPeriod->id !== $newPayPeriod->id) {
                        $this->dispatchRecalculationForEmployee($attendance->employee_id, $oldPunchTime);
                    }
                }
            }
        }
    }

    /**
     * Handle the Attendance "deleted" event.
     */
    public function deleted(Attendance $attendance): void
    {
        $this->dispatchRecalculation($attendance);
    }

    /**
     * Handle the Attendance "restored" event (if using soft deletes).
     */
    public function restored(Attendance $attendance): void
    {
        $this->dispatchRecalculation($attendance);
    }

    /**
     * Handle the Attendance "force deleted" event.
     */
    public function forceDeleted(Attendance $attendance): void
    {
        $this->dispatchRecalculation($attendance);
    }

    /**
     * Dispatch the recalculation job for the attendance record.
     */
    protected function dispatchRecalculation(Attendance $attendance): void
    {
        if (! $attendance->employee_id || ! $attendance->punch_time) {
            return;
        }

        $payPeriod = $this->findPayPeriodForDate($attendance->punch_time);

        if (! $payPeriod) {
            Log::debug("[AttendanceObserver] No pay period found for date {$attendance->punch_time}");

            return;
        }

        // Only recalculate for posted or processing pay periods
        // Skip if pay period is still open (summaries haven't been generated yet)
        if (! $this->shouldRecalculate($payPeriod, $attendance)) {
            return;
        }

        Log::debug("[AttendanceObserver] Dispatching recalculation for Employee {$attendance->employee_id}, PayPeriod {$payPeriod->id}");

        RecalculateEmployeeSummaryJob::dispatch($attendance->employee_id, $payPeriod->id);
    }

    /**
     * Dispatch recalculation for a specific employee and date.
     */
    protected function dispatchRecalculationForEmployee(int $employeeId, string $punchTime): void
    {
        $payPeriod = $this->findPayPeriodForDate($punchTime);

        if (! $payPeriod) {
            return;
        }

        if (! $this->shouldRecalculateForPeriod($payPeriod)) {
            return;
        }

        Log::debug("[AttendanceObserver] Dispatching recalculation for Employee {$employeeId}, PayPeriod {$payPeriod->id} (original record)");

        RecalculateEmployeeSummaryJob::dispatch($employeeId, $payPeriod->id);
    }

    /**
     * Find the pay period that contains the given date.
     */
    protected function findPayPeriodForDate(string $date): ?PayPeriod
    {
        return PayPeriod::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /**
     * Determine if we should recalculate summaries for this attendance change.
     */
    protected function shouldRecalculate(PayPeriod $payPeriod, Attendance $attendance): bool
    {
        // Only recalculate for attendance records with Complete/Posted/Migrated status
        // Skip incomplete or needs-review records as they're not part of final calculations
        $validStatuses = ['Complete', 'Posted', 'Migrated'];

        if (! in_array($attendance->status, $validStatuses)) {
            // But if we're deleting or the status changed TO a valid status, still recalculate
            if (! $attendance->wasChanged('status') && ! $attendance->trashed()) {
                return false;
            }
        }

        return $this->shouldRecalculateForPeriod($payPeriod);
    }

    /**
     * Determine if summaries exist for this pay period (i.e., aggregation has run).
     */
    protected function shouldRecalculateForPeriod(PayPeriod $payPeriod): bool
    {
        // Check if the pay period has been processed (has summaries or is posted)
        // This prevents recalculating for pay periods that haven't been aggregated yet
        if ($payPeriod->is_posted) {
            return true;
        }

        // Check if summaries exist for this pay period
        return $payPeriod->employeeSummaries()->exists();
    }
}
