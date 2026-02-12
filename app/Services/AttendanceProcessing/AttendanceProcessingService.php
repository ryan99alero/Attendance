<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Services\HolidayProcessing\HolidayAttendanceProcessor;
use App\Services\TimeGrouping\AttendanceTimeProcessorService;
use App\Services\VacationProcessing\VacationTimeProcessAttendanceService;
use DB;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

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

    protected AttendanceClassificationService $attendanceClassificationService;

    public function __construct(
        HolidayAttendanceProcessor $holidayAttendanceProcessor,
        VacationTimeProcessAttendanceService $vacationTimeProcessAttendanceService,
        AttendanceTimeProcessorService $attendanceTimeProcessorService,
        AttendanceCleansingService $attendanceCleansingService,
        PunchValidationService $punchValidationService,
        PunchMigrationService $punchMigrationService,
        UnresolvedAttendanceProcessorService $unresolvedAttendanceProcessorService,
        AttendanceStatusUpdateService $attendanceStatusUpdateService,
        AttendanceClassificationService $attendanceClassificationService
    ) {
        Log::info('[AttendanceProcessing] Initializing AttendanceProcessingService...');
        $this->holidayAttendanceProcessor = $holidayAttendanceProcessor;
        $this->vacationTimeProcessAttendanceService = $vacationTimeProcessAttendanceService;
        $this->attendanceTimeProcessorService = $attendanceTimeProcessorService;
        $this->attendanceCleansingService = $attendanceCleansingService;
        $this->punchValidationService = $punchValidationService;
        $this->punchMigrationService = $punchMigrationService;
        $this->unresolvedAttendanceProcessorService = $unresolvedAttendanceProcessorService;
        $this->attendanceStatusUpdateService = $attendanceStatusUpdateService;
        $this->attendanceClassificationService = $attendanceClassificationService;
    }

    public function processAll(PayPeriod $payPeriod): void
    {
        Log::info("[AttendanceProcessing] 🚀 Starting attendance processing for PayPeriod ID: {$payPeriod->id}");

        // Check if vacation records exist before processing
        $vacationRecordsBefore = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('classification_id', DB::table('classifications')->where('code', 'VACATION')->value('id'))
            ->where('status', 'Complete')
            ->count();

        $this->runProcessingSteps($payPeriod);

        // Check if new vacation records were created during processing
        $vacationRecordsAfter = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('classification_id', DB::table('classifications')->where('code', 'VACATION')->value('id'))
            ->where('status', 'Complete')
            ->count();

        // If vacation records were created, run processing again to ensure they're properly migrated
        if ($vacationRecordsAfter > $vacationRecordsBefore) {
            $newVacationCount = $vacationRecordsAfter - $vacationRecordsBefore;
            Log::info("[AttendanceProcessing] 🔄 {$newVacationCount} new vacation records detected, running second processing pass...");

            Notification::make()
                ->info()
                ->title('Vacation Records Detected')
                ->body("Found {$newVacationCount} vacation records - automatically running second pass to ensure proper migration...")
                ->duration(5000)
                ->send();

            $this->runProcessingSteps($payPeriod);

            // Final confirmation
            Log::info('[AttendanceProcessing] ✅ Second pass completed - vacation records should now be properly migrated');

            Notification::make()
                ->success()
                ->title('Vacation Processing Complete')
                ->body('Vacation records have been processed and migrated successfully!')
                ->duration(3000)
                ->send();
        }

        Log::info("[AttendanceProcessing] 🎯 All processing completed for PayPeriod ID: {$payPeriod->id}");
    }

    private function runProcessingSteps(PayPeriod $payPeriod): void
    {
        $steps = [
            [
                'name' => 'Removing duplicate records',
                'action' => fn () => $this->attendanceCleansingService->cleanUpDuplicates(),
            ],
            [
                'name' => 'Processing vacation records',
                'action' => fn () => $this->vacationTimeProcessAttendanceService->processVacationDays($payPeriod->start_date, $payPeriod->end_date),
            ],
            [
                'name' => 'Processing holiday records',
                'action' => fn () => method_exists($this->holidayAttendanceProcessor, 'processHolidaysForPayPeriod')
                    ? $this->holidayAttendanceProcessor->processHolidaysForPayPeriod($payPeriod)
                    : Log::error('[AttendanceProcessing] processHolidaysForPayPeriod() method not found'),
            ],
            [
                'name' => 'Processing attendance time records',
                'action' => fn () => $this->attendanceTimeProcessorService->processAttendanceForPayPeriod($payPeriod),
            ],
            [
                'name' => 'Classifying attendance records',
                'action' => fn () => $this->attendanceClassificationService->classifyAllUnclassifiedAttendance(),
            ],
            [
                'name' => 'Processing unresolved records',
                'action' => fn () => $this->unresolvedAttendanceProcessorService->processStalePartialRecords($payPeriod),
            ],
            [
                'name' => 'Validating punch records',
                'action' => fn () => $this->punchValidationService->validatePunchesWithinPayPeriod($payPeriod),
            ],
            [
                'name' => 'Resolving overlapping records',
                'action' => fn () => $this->punchValidationService->resolveOverlappingRecords($payPeriod),
            ],
            [
                'name' => 'Re-evaluating review records',
                'action' => fn () => $this->attendanceStatusUpdateService->reevaluateNeedsReviewRecords($payPeriod),
            ],
            [
                'name' => 'Migrating final punch records',
                'action' => fn () => $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod),
            ],
        ];

        $totalSteps = count($steps);

        foreach ($steps as $index => $step) {
            $stepNumber = $index + 1;
            $stepName = $step['name'];

            // Update PayPeriod progress (0-100 scale, reserve 10% for start and 10% for end)
            $progress = 10 + (int) (($index / $totalSteps) * 80);
            $payPeriod->updateProgress($progress, "Step {$stepNumber}/{$totalSteps}: {$stepName}");

            Log::info("[AttendanceProcessing] Step {$stepNumber}/{$totalSteps}: {$stepName}");

            try {
                $step['action']();
                Log::info("[AttendanceProcessing] Step {$stepNumber}: {$stepName} completed.");
            } catch (Exception $e) {
                Log::error("[AttendanceProcessing] Step {$stepNumber}: {$stepName} failed: ".$e->getMessage());
                throw $e;
            }
        }

        // Mark as 90% after all steps (final 10% reserved for job completion)
        $payPeriod->updateProgress(90, 'Finalizing...');

        Log::info("[AttendanceProcessing] Processing steps completed for PayPeriod ID: {$payPeriod->id}");
    }

    public function processCompletedAttendanceRecords(array $attendanceIds, bool $autoProcess): void
    {
        Log::info('[AttendanceProcessing] 🛠 Processing completed attendance records.');

        // ✅ Mark records as Complete
        $this->attendanceStatusUpdateService->markRecordsAsComplete($attendanceIds);
        Log::info('[AttendanceProcessing] ✅ Attendance records marked as Complete.');

        // ✅ Only trigger migration if Auto-Process is enabled
        if ($autoProcess) {
            Log::info('[AttendanceProcessing] 🚀 Auto-Process enabled. Triggering Punch Migration Service.');

            // ✅ Get PayPeriod from first attendance record
            $firstAttendance = Attendance::find($attendanceIds[0] ?? 0);
            $payPeriod = $firstAttendance ? PayPeriod::current() : null;
            if ($payPeriod instanceof PayPeriod) {
                $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod);
                Log::info('[AttendanceProcessing] ✅ Punch migration completed.');
            } else {
                Log::warning('[AttendanceProcessing] ⚠️ No valid PayPeriod found for Attendance IDs: '.json_encode($attendanceIds));
            }
        } else {
            Log::info('[AttendanceProcessing] ⏸ Auto-Process disabled. Skipping Punch Migration.');
        }
    }
}
