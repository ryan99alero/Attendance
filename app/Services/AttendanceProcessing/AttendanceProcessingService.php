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

        // Cleanse department schedules
        $groupService = new AttendanceCleansingGroupService();
        $groupService->processDepartmentSchedulesWithinPayPeriod($payPeriod);

        // Validate and process punches
        $validationService = new PunchValidationService();
        $validationService->validatePunchesWithinPayPeriod($payPeriod);
    }
}
