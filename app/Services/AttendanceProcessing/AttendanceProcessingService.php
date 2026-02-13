<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Services\HolidayProcessing\HolidayAttendanceProcessor;
use App\Services\TimeGrouping\AttendanceTimeProcessorService;
use App\Services\VacationProcessing\VacationTimeProcessAttendanceService;
use Exception;
use Illuminate\Support\Facades\DB;
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

    protected ?int $vacationClassificationId = null;

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
        // CRITICAL: Disable query logging at the very start to prevent memory exhaustion
        DB::disableQueryLog();

        Log::info("[AttendanceProcessingService] Starting processing for PayPeriod ID: {$payPeriod->id}");

        // Cache vacation classification ID
        if ($this->vacationClassificationId === null) {
            $this->vacationClassificationId = DB::table('classifications')->where('code', 'VACATION')->value('id');
        }

        $vacationRecordsBefore = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('classification_id', $this->vacationClassificationId)
            ->where('status', 'Complete')
            ->count();

        $this->runProcessingSteps($payPeriod);

        $vacationRecordsAfter = Attendance::whereBetween('punch_time', [$payPeriod->start_date, $payPeriod->end_date])
            ->where('classification_id', $this->vacationClassificationId)
            ->where('status', 'Complete')
            ->count();

        if ($vacationRecordsAfter > $vacationRecordsBefore) {
            $this->runProcessingSteps($payPeriod);
        }

        Log::info("[AttendanceProcessingService] Completed processing for PayPeriod ID: {$payPeriod->id}");
    }

    private function runProcessingSteps(PayPeriod $payPeriod): void
    {
        // Ensure query logging stays disabled throughout all steps
        DB::disableQueryLog();

        $steps = [
            ['name' => 'Removing duplicates', 'action' => fn () => $this->attendanceCleansingService->cleanUpDuplicates()],
            ['name' => 'Processing vacation', 'action' => fn () => $this->vacationTimeProcessAttendanceService->processVacationDays($payPeriod->start_date, $payPeriod->end_date)],
            ['name' => 'Processing holidays', 'action' => fn () => $this->holidayAttendanceProcessor->processHolidaysForPayPeriod($payPeriod)],
            ['name' => 'Processing attendance', 'action' => fn () => $this->attendanceTimeProcessorService->processAttendanceForPayPeriod($payPeriod)],
            ['name' => 'Classifying records', 'action' => fn () => $this->attendanceClassificationService->classifyAllUnclassifiedAttendance()],
            ['name' => 'Processing unresolved', 'action' => fn () => $this->unresolvedAttendanceProcessorService->processStalePartialRecords($payPeriod)],
            ['name' => 'Validating punches', 'action' => fn () => $this->punchValidationService->validatePunchesWithinPayPeriod($payPeriod)],
            ['name' => 'Resolving overlaps', 'action' => fn () => $this->punchValidationService->resolveOverlappingRecords($payPeriod)],
            ['name' => 'Re-evaluating reviews', 'action' => fn () => $this->attendanceStatusUpdateService->reevaluateNeedsReviewRecords($payPeriod)],
            ['name' => 'Migrating punches', 'action' => fn () => $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod)],
        ];

        $totalSteps = count($steps);

        foreach ($steps as $index => $step) {
            $progress = 10 + (int) (($index / $totalSteps) * 80);
            $payPeriod->updateProgress($progress, 'Step '.($index + 1)."/{$totalSteps}: {$step['name']}");

            try {
                $step['action']();
            } catch (Exception $e) {
                Log::error("[AttendanceProcessingService] Step {$step['name']} failed: ".$e->getMessage());
                throw $e;
            }
        }

        $payPeriod->updateProgress(90, 'Finalizing...');
    }

    public function processCompletedAttendanceRecords(array $attendanceIds, bool $autoProcess): void
    {
        DB::disableQueryLog();

        $this->attendanceStatusUpdateService->markRecordsAsComplete($attendanceIds);

        if ($autoProcess) {
            $firstAttendance = Attendance::find($attendanceIds[0] ?? 0);
            $payPeriod = $firstAttendance ? PayPeriod::current() : null;
            if ($payPeriod instanceof PayPeriod) {
                $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod);
            }
        }
    }
}
