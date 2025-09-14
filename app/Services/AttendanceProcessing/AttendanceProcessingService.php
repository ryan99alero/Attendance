<?php

namespace App\Services\AttendanceProcessing;

use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;
use App\Services\HolidayProcessing\HolidayAttendanceProcessor;
use App\Services\VacationProcessing\VacationTimeProcessAttendanceService;
use App\Services\TimeGrouping\AttendanceTimeProcessorService;

class AttendanceProcessingService
{
    protected HolidayAttendanceProcessor $holidayAttendanceProcessor;
    protected VacationTimeProcessAttendanceService $vacationTimeProcessAttendanceService;
    protected AttendanceTimeProcessorService $attendanceTimeProcessorService;
    protected AttendanceCleansingService $attendanceCleansingService;
    protected PunchValidationService $punchValidationService;
    protected PunchMigrationService $punchMigrationService;
    protected UnresolvedAttendanceProcessorService $unresolvedAttendanceProcessorService;
    protected AttendanceStatusUpdateService $attendanceStatusUpdateService;

    public function __construct(
        HolidayAttendanceProcessor $holidayAttendanceProcessor,
        VacationTimeProcessAttendanceService $vacationTimeProcessAttendanceService,
        AttendanceTimeProcessorService $attendanceTimeProcessorService,
        AttendanceCleansingService $attendanceCleansingService,
        PunchValidationService $punchValidationService,
        PunchMigrationService $punchMigrationService,
        UnresolvedAttendanceProcessorService $unresolvedAttendanceProcessorService,
        AttendanceStatusUpdateService $attendanceStatusUpdateService
    ) {
        Log::info("[AttendanceProcessing] Initializing AttendanceProcessingService...");
        $this->holidayAttendanceProcessor = $holidayAttendanceProcessor;
        $this->vacationTimeProcessAttendanceService = $vacationTimeProcessAttendanceService;
        $this->attendanceTimeProcessorService = $attendanceTimeProcessorService;
        $this->attendanceCleansingService = $attendanceCleansingService;
        $this->punchValidationService = $punchValidationService;
        $this->punchMigrationService = $punchMigrationService;
        $this->unresolvedAttendanceProcessorService = $unresolvedAttendanceProcessorService;
        $this->attendanceStatusUpdateService = $attendanceStatusUpdateService;
    }

    public function processAll(PayPeriod $payPeriod): void
    {
        Log::info("[AttendanceProcessing] 🚀 Starting attendance processing for PayPeriod ID: {$payPeriod->id}");

        $steps = [
            [
                'name' => 'Removing duplicate records',
                'action' => fn() => $this->attendanceCleansingService->cleanUpDuplicates()
            ],
            [
                'name' => 'Processing vacation records',
                'action' => fn() => $this->vacationTimeProcessAttendanceService->processVacationDays($payPeriod->start_date, $payPeriod->end_date)
            ],
            [
                'name' => 'Processing holiday records',
                'action' => fn() => method_exists($this->holidayAttendanceProcessor, 'processHolidaysForPayPeriod')
                    ? $this->holidayAttendanceProcessor->processHolidaysForPayPeriod($payPeriod)
                    : Log::error("[AttendanceProcessing] processHolidaysForPayPeriod() method not found")
            ],
            [
                'name' => 'Processing attendance time records',
                'action' => fn() => $this->attendanceTimeProcessorService->processAttendanceForPayPeriod($payPeriod)
            ],
            [
                'name' => 'Processing unresolved records',
                'action' => fn() => $this->unresolvedAttendanceProcessorService->processStalePartialRecords($payPeriod)
            ],
            [
                'name' => 'Validating punch records',
                'action' => fn() => $this->punchValidationService->validatePunchesWithinPayPeriod($payPeriod)
            ],
            [
                'name' => 'Resolving overlapping records',
                'action' => fn() => $this->punchValidationService->resolveOverlappingRecords($payPeriod)
            ],
            [
                'name' => 'Re-evaluating review records',
                'action' => fn() => $this->attendanceStatusUpdateService->reevaluateNeedsReviewRecords($payPeriod)
            ],
            [
                'name' => 'Migrating final punch records',
                'action' => fn() => $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod)
            ]
        ];

        $totalSteps = count($steps);

        foreach ($steps as $index => $step) {
            $stepNumber = $index + 1;
            $stepName = $step['name'];

            Log::info("[AttendanceProcessing] 🔍 Step {$stepNumber}/{$totalSteps}: {$stepName}");

            // Send progress notification
            \Filament\Notifications\Notification::make()
                ->info()
                ->title("Processing Step {$stepNumber}/{$totalSteps}")
                ->body($stepName)
                ->send();

            try {
                $step['action']();
                Log::info("[AttendanceProcessing] ✅ Step {$stepNumber}: {$stepName} completed.");
            } catch (\Exception $e) {
                Log::error("[AttendanceProcessing] ❌ Step {$stepNumber}: {$stepName} failed: " . $e->getMessage());

                // Send error notification and re-throw
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title("Step {$stepNumber} Failed")
                    ->body("Error in {$stepName}: " . $e->getMessage())
                    ->persistent()
                    ->send();

                throw $e;
            }
        }

        Log::info("[AttendanceProcessing] 🎯 All {$totalSteps} processing steps completed for PayPeriod ID: {$payPeriod->id}");
    }

    public function processCompletedAttendanceRecords(array $attendanceIds, bool $autoProcess): void
    {
        Log::info("[AttendanceProcessing] 🛠 Processing completed attendance records.");

        // ✅ Mark records as Complete
        $this->attendanceStatusUpdateService->markRecordsAsComplete($attendanceIds);
        Log::info("[AttendanceProcessing] ✅ Attendance records marked as Complete.");

        // ✅ Only trigger migration if Auto-Process is enabled
        if ($autoProcess) {
            Log::info("[AttendanceProcessing] 🚀 Auto-Process enabled. Triggering Punch Migration Service.");

            // ✅ Fix PayPeriod fetch logic
            $payPeriod = PayPeriod::find($attendanceIds[0] ?? 0);
            if ($payPeriod instanceof PayPeriod) {
                $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod);
                Log::info("[AttendanceProcessing] ✅ Punch migration completed.");
            } else {
                Log::warning("[AttendanceProcessing] ⚠️ No valid PayPeriod found for Attendance IDs: " . json_encode($attendanceIds));
            }
        } else {
            Log::info("[AttendanceProcessing] ⏸ Auto-Process disabled. Skipping Punch Migration.");
        }
    }
}
