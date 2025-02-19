<?php

namespace App\Services\AttendanceProcessing;

use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;
use App\Services\HolidayProcessing\{
    HolidayProcessingService,
    HolidayAttendanceProcessor
};
use App\Services\VacationProcessing\VacationTimeProcessAttendanceService;
use App\Services\TimeGrouping\{
    AttendanceTimeProcessorService,
    AttendanceTimeGroupService
};


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

    /**
     * Constructor to inject dependencies.
     */
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
        Log::info("Initializing AttendanceProcessingService...");
        $this->holidayAttendanceProcessor = $holidayAttendanceProcessor;
        $this->vacationTimeProcessAttendanceService = $vacationTimeProcessAttendanceService;
        $this->attendanceTimeProcessorService = $attendanceTimeProcessorService;
        $this->attendanceCleansingService = $attendanceCleansingService;
        $this->punchValidationService = $punchValidationService;
        $this->punchMigrationService = $punchMigrationService;
        $this->unresolvedAttendanceProcessorService = $unresolvedAttendanceProcessorService;
        $this->attendanceStatusUpdateService = $attendanceStatusUpdateService;
    }

    /**
     * Process all attendance records for the given PayPeriod.
     */
    public function processAll(PayPeriod $payPeriod): void
    {
        Log::info("🚀 Starting attendance processing for PayPeriod ID: {$payPeriod->id}");

        // Step 1: Run Attendance Cleansing Service
        Log::info("🔍 Step 1: Running AttendanceCleansingService to remove duplicates.");
        $this->attendanceCleansingService->cleanUpDuplicates();
        Log::info("✅ Step 1: AttendanceCleansingService completed.");

        // Step 2: Process Vacation Records
        Log::info("🔍 Step 2: Processing vacation attendance records.");
        $this->vacationTimeProcessAttendanceService->processVacationDays($payPeriod->start_date, $payPeriod->end_date);
        Log::info("✅ Step 2: Vacation processing completed.");

        // Step 3: Process Holiday Records
        Log::info("🔍 Step 3: Processing holiday attendance records.");
        if (method_exists($this->holidayAttendanceProcessor, 'processHolidaysForPayPeriod')) {
            $this->holidayAttendanceProcessor->processHolidaysForPayPeriod($payPeriod);
            Log::info("✅ Step 3: Holiday processing completed.");
        } else {
            Log::error("🚨 [processHolidaysForPayPeriod] method does not exist in HolidayAttendanceProcessor.");
        }

        // Step 4: Process Attendance Time Records
        Log::info("🔍 Step 4: Processing attendance time records.");
        $this->attendanceTimeProcessorService->processAttendanceForPayPeriod($payPeriod);
        Log::info("✅ Step 4: Attendance time processing completed.");

        // Step 5: Validate Punches
        Log::info("🔍 Step 5: Validating punches.");
        $this->punchValidationService->validatePunchesWithinPayPeriod($payPeriod);
        Log::info("✅ Step 5: Punch validation completed.");

        // Step 6: Resolve overlapping records
        Log::info("🔍 Step 6: Resolving overlapping attendance records.");
        $this->punchValidationService->resolveOverlappingRecords($payPeriod);
        Log::info("✅ Step 6: Overlapping records resolved.");

        // Step 7: Migrate Punches
        Log::info("🔍 Step 7: .");
        $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod);
        Log::info("✅ Step 7: Punch migration completed.");

        // Step 8: Process Unresolved Partial Records
        Log::info("🔍 Step 8: Processing unresolved attendance records.");
        $this->unresolvedAttendanceProcessorService->processStalePartialRecords($payPeriod);
        Log::info("✅ Step 8: Unresolved attendance processing completed.");

        Log::info("🎯 Attendance processing completed for PayPeriod ID: {$payPeriod->id}");
    }

    /**
     * Process completed attendance records by marking them as "Complete".
     */
    public function processCompletedAttendanceRecords(array $attendanceIds, bool $autoProcess): void
    {
        Log::info("🛠 [processCompletedAttendanceRecords] Calling AttendanceStatusUpdateService.");

        // ✅ Mark records as Complete
        $this->attendanceStatusUpdateService->markRecordsAsComplete($attendanceIds);
        Log::info("✅ [processCompletedAttendanceRecords] Attendance records marked as Complete.");

        // ✅ Only trigger migration if Auto-Process is enabled
        if ($autoProcess) {
            Log::info("🚀 [processCompletedAttendanceRecords] Auto-Process is enabled. Triggering Punch Migration Service.");

            // ✅ Fix PayPeriod fetch logic
            $payPeriod = PayPeriod::find($attendanceIds[0] ?? 0);
            if ($payPeriod instanceof PayPeriod) {
                $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod);
                Log::info("✅ [processCompletedAttendanceRecords] Punch migration completed.");
            } else {
                Log::warning("⚠️ No valid PayPeriod found for Attendance IDs: " . json_encode($attendanceIds));
            }
        } else {
            Log::info("⏸ [processCompletedAttendanceRecords] Auto-Process is disabled. Skipping Punch Migration.");
        }
    }
}
