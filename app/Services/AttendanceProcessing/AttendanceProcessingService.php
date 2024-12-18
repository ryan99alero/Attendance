<?php

namespace App\Services\AttendanceProcessing;

use App\Models\PayPeriod;

class AttendanceProcessingService
{
    /**
     * Process all attendance data within the given pay period.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function processAll(PayPeriod $payPeriod): void
    {
        // Cleanse user schedules
        $userService = new AttendanceCleansingUserService();
        $userService->processUserSchedulesWithinPayPeriod($payPeriod);

        // Cleanse group/department schedules
        $groupService = new AttendanceCleansingGroupService();
        $groupService->processGroupSchedulesWithinPayPeriod($payPeriod);

        // Validate attendance punches and prepare for migration
        $validationService = new PunchValidationService();
        $validationService->validatePunchesWithinPayPeriod($payPeriod);

        // Migrate punches to the Punches table
        $migrationService = new PunchMigrationService();
        $migrationService->migratePunchesWithinPayPeriod($payPeriod);
    }
}
