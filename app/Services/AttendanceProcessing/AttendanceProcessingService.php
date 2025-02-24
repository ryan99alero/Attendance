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

        // Step 1: Run Attendance Cleansing Service
        Log::info("[AttendanceProcessing] 🔍 Step 1: Running AttendanceCleansingService to remove duplicates.");
        $this->attendanceCleansingService->cleanUpDuplicates();
        Log::info("[AttendanceProcessing] ✅ Step 1: Attendance Cleansing completed.");

        // Step 2: Process Vacation Records
        Log::info("[AttendanceProcessing] 🔍 Step 2: Processing vacation attendance records.");
        $this->vacationTimeProcessAttendanceService->processVacationDays($payPeriod->start_date, $payPeriod->end_date);
        Log::info("[AttendanceProcessing] ✅ Step 2: Vacation processing completed.");

        // Step 3: Process Holiday Records
        Log::info("[AttendanceProcessing] 🔍 Step 3: Processing holiday attendance records.");
        if (method_exists($this->holidayAttendanceProcessor, 'processHolidaysForPayPeriod')) {
            $this->holidayAttendanceProcessor->processHolidaysForPayPeriod($payPeriod);
            Log::info("[AttendanceProcessing] ✅ Step 3: Holiday processing completed.");
        } else {
            Log::error("[AttendanceProcessing] 🚨 processHolidaysForPayPeriod() does not exist in HolidayAttendanceProcessor.");
        }

        // Step 4: Process Attendance Time Records
        Log::info("[AttendanceProcessing] 🔍 Step 4: Processing attendance time records.");
        $this->attendanceTimeProcessorService->processAttendanceForPayPeriod($payPeriod);
        Log::info("[AttendanceProcessing] ✅ Step 4: Attendance time processing completed.");

        // Step 5: Process Unresolved Attendance BEFORE Validation & Migration
        Log::info("[AttendanceProcessing] 🔍 Step 5: Processing unresolved attendance records.");
        $this->unresolvedAttendanceProcessorService->processStalePartialRecords($payPeriod);
        Log::info("[AttendanceProcessing] ✅ Step 5: Unresolved attendance processing completed.");

        // Step 6: Validate Punches
        Log::info("[AttendanceProcessing] 🔍 Step 6: Validating punches.");
        $this->punchValidationService->validatePunchesWithinPayPeriod($payPeriod);
        Log::info("[AttendanceProcessing] ✅ Step 6: Punch validation completed.");

        // Step 7: Resolve overlapping records
        Log::info("[AttendanceProcessing] 🔍 Step 7: Resolving overlapping attendance records.");
        $this->punchValidationService->resolveOverlappingRecords($payPeriod);
        Log::info("[AttendanceProcessing] ✅ Step 7: Overlapping records resolved.");

        // Step 8: Migrate Punches
        Log::info("[AttendanceProcessing] 🔍 Step 8: Migrating punches.");
        $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod);
        Log::info("[AttendanceProcessing] ✅ Step 8: Punch migration completed.");

        Log::info("[AttendanceProcessing] 🎯 Attendance processing completed for PayPeriod ID: {$payPeriod->id}");
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
