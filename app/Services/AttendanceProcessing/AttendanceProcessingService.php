<?php

namespace App\Services\AttendanceProcessing;

use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;

class AttendanceProcessingService
{
    protected HolidayProcessingService $holidayProcessingService;
    protected VacationTimeProcessAttendanceService $vacationTimeProcessAttendanceService;
    protected AttendanceTimeProcessorService $attendanceTimeProcessorService;
    protected AttendanceCleansingService $attendanceCleansingService;
    protected PunchValidationService $punchValidationService;
    protected PunchMigrationService $punchMigrationService;
    protected UnresolvedAttendanceProcessorService $unresolvedAttendanceProcessorService;
    protected AttendanceStatusUpdateService $attendanceStatusUpdateService; // ✅ NEW

    /**
     * Constructor to inject dependencies.
     */
    public function __construct(
        HolidayProcessingService $holidayProcessingService,
        VacationTimeProcessAttendanceService $vacationTimeProcessAttendanceService,
        AttendanceTimeProcessorService $attendanceTimeProcessorService,
        AttendanceCleansingService $attendanceCleansingService,
        PunchValidationService $punchValidationService,
        PunchMigrationService $punchMigrationService,
        UnresolvedAttendanceProcessorService $unresolvedAttendanceProcessorService,
        AttendanceStatusUpdateService $attendanceStatusUpdateService // ✅ Inject Service
    ) {
        Log::info("Initializing AttendanceProcessingService...");
        $this->holidayProcessingService = $holidayProcessingService;
        $this->vacationTimeProcessAttendanceService = $vacationTimeProcessAttendanceService;
        $this->attendanceTimeProcessorService = $attendanceTimeProcessorService;
        $this->attendanceCleansingService = $attendanceCleansingService;
        $this->punchValidationService = $punchValidationService;
        $this->punchMigrationService = $punchMigrationService;
        $this->unresolvedAttendanceProcessorService = $unresolvedAttendanceProcessorService;
        $this->attendanceStatusUpdateService = $attendanceStatusUpdateService; // ✅ Store Service
    }

    /**
     * Process all attendance records for the given PayPeriod.
     */
    public function processAll(PayPeriod $payPeriod): void
    {
        Log::info("Starting attendance processing for PayPeriod ID: {$payPeriod->id}");

        // Step 1: Run Attendance Cleansing Service
        Log::info("Running AttendanceCleansingService to remove duplicates.");
        $this->attendanceCleansingService->cleanUpDuplicates();
        Log::info("AttendanceCleansingService completed.");

        // Step 2: Process Vacation Records
        Log::info("Processing vacation attendance records.");
        $this->vacationTimeProcessAttendanceService->processVacationDays($payPeriod->start_date, $payPeriod->end_date);

        // Step 3: Process Holiday Records
        Log::info("Processing holiday attendance records.");
        $this->holidayProcessingService->processHolidays($payPeriod->start_date, $payPeriod->end_date);

        // Step 4: Process Attendance Time Records
        Log::info("Processing attendance time records for PayPeriod.");
        $this->attendanceTimeProcessorService->processAttendanceForPayPeriod($payPeriod);
        Log::info("Attendance time processing completed.");

        // Step 5: Validate Punches
        Log::info("Validating punches for PayPeriod.");
        $this->punchValidationService->validatePunchesWithinPayPeriod($payPeriod);

        // Step 6: Resolve overlapping records
        Log::info("Resolving overlapping attendance records.");
        $this->punchValidationService->resolveOverlappingRecords($payPeriod);

        // Step 7: Migrate Punches
        Log::info("Preparing to migrate punches for PayPeriod. This step will ensure punches are migrated and attendances are marked as processed.");
        Log::info("Migrating punches for PayPeriod.");
        $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod);

        // Step 8: Process Unresolved Partial Records
        Log::info("Processing unresolved attendance records for PayPeriod ID: {$payPeriod->id}");
        $this->unresolvedAttendanceProcessorService->processStalePartialRecords($payPeriod);
        Log::info("Unresolved attendance processing completed.");

        Log::info("Attendance processing completed for PayPeriod ID: {$payPeriod->id}");
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
            $this->punchMigrationService->migratePunchesForAttendances($attendanceIds);
            Log::info("✅ [processCompletedAttendanceRecords] Punch migration completed.");
        } else {
            Log::info("⏸ [processCompletedAttendanceRecords] Auto-Process is disabled. Skipping Punch Migration.");
        }
    }
}
