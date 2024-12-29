<?php

namespace App\Services\AttendanceProcessing;

use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;

class AttendanceProcessingService
{
    protected PunchMigrationService $punchMigrationService;

    /**
     * Constructor to inject PunchMigrationService.
     *
     * @param PunchMigrationService $punchMigrationService
     */
    public function __construct(PunchMigrationService $punchMigrationService)
    {
        $this->punchMigrationService = $punchMigrationService;
    }

    /**
     * Process all attendance records for the given PayPeriod.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function processAll(PayPeriod $payPeriod): void
    {
        Log::info("Starting Attendance Processing for PayPeriod: {$payPeriod->id}");

        // Process User-specific Attendance Records
        $userService = new AttendanceImportUserService();
        $userService->processUserSchedulesWithinPayPeriod($payPeriod);

        // Process Group-specific Attendance Records
        $groupService = new AttendanceImportGroupService();
        $groupService->processGroupSchedulesWithinPayPeriod($payPeriod);

        // Process Vacation Records
//        $this->processVacationRecords($payPeriod);

        // Validate punches
        $validationService = new PunchValidationService();
        $validationService->validatePunchesWithinPayPeriod($payPeriod);

        // Migrate punches to the Punch table
        $this->punchMigrationService->migratePunchesWithinPayPeriod($payPeriod);

        Log::info("Attendance Processing completed for PayPeriod: {$payPeriod->id}");
    }

    /**
     * Process Vacation Records for the given PayPeriod.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    private function processVacationRecords(PayPeriod $payPeriod): void
    {
        Log::info("Processing Vacation Records for PayPeriod: {$payPeriod->id}");

        $vacationService = new VacationTimeProcessAttendanceService();
        $vacationService->processVacationDays([$payPeriod->start_date, $payPeriod->end_date]);

        Log::info("Vacation Records processed for PayPeriod: {$payPeriod->id}");
    }
}
