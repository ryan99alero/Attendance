<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\OvertimeCalculationLog;
use App\Models\PayPeriod;
use App\Models\PayPeriodEmployeeSummary;
use App\Services\Payroll\PayrollAggregationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Recalculates payroll summaries for a single employee within a pay period.
 *
 * This job is dispatched when attendance records are created, updated, or deleted
 * to ensure pay_period_employee_summaries and overtime_calculation_logs stay in sync.
 *
 * Implements ShouldBeUnique to prevent duplicate recalculations for the same
 * employee/pay period combination within the uniqueFor window.
 */
class RecalculateEmployeeSummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $employeeId,
        public int $payPeriodId
    ) {}

    /**
     * The unique ID of the job (prevents duplicate jobs for same employee/period).
     */
    public function uniqueId(): string
    {
        return "recalc_{$this->employeeId}_{$this->payPeriodId}";
    }

    /**
     * How long to maintain uniqueness (debounce multiple rapid edits).
     */
    public function uniqueFor(): int
    {
        return 10; // 10 seconds - combines rapid edits into one recalculation
    }

    /**
     * Execute the job.
     */
    public function handle(PayrollAggregationService $aggregationService): void
    {
        $employee = Employee::find($this->employeeId);
        $payPeriod = PayPeriod::find($this->payPeriodId);

        if (! $employee || ! $payPeriod) {
            Log::warning("[RecalculateEmployeeSummary] Employee {$this->employeeId} or PayPeriod {$this->payPeriodId} not found");

            return;
        }

        Log::info("[RecalculateEmployeeSummary] Recalculating for Employee {$employee->external_id} in PayPeriod {$payPeriod->name}");

        // Step 1: Delete existing summaries for this employee/pay period
        $deletedSummaries = PayPeriodEmployeeSummary::where('pay_period_id', $this->payPeriodId)
            ->where('employee_id', $this->employeeId)
            ->delete();

        // Step 2: Delete existing overtime calculation logs for this employee/pay period
        $deletedLogs = OvertimeCalculationLog::where('pay_period_id', $this->payPeriodId)
            ->where('employee_id', $this->employeeId)
            ->delete();

        Log::debug("[RecalculateEmployeeSummary] Deleted {$deletedSummaries} summaries and {$deletedLogs} overtime logs");

        // Step 3: Re-aggregate this employee's hours
        try {
            $summary = $aggregationService->aggregateEmployeeHours($employee, $payPeriod);

            Log::info("[RecalculateEmployeeSummary] Completed for Employee {$employee->external_id}: ".
                "Regular={$summary['regular_hours']}, OT={$summary['overtime_hours']}, ".
                "Holiday={$summary['holiday_hours']}, Vacation={$summary['vacation_hours']}");
        } catch (\Exception $e) {
            Log::error("[RecalculateEmployeeSummary] Failed for Employee {$employee->external_id}: {$e->getMessage()}");
            throw $e;
        }
    }
}
