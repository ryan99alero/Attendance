<?php

namespace App\Services\AttendanceProcessing;

use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;

class AttendanceProcessingService
{
    protected HolidayProcessingService $holidayProcessingService;
    protected VacationTimeProcessAttendanceService $vacationTimeProcessAttendanceService;
    protected AttendanceTimeProcessorService $attendanceTimeProcessorService;

    /**
     * Constructor to inject dependencies.
     *
     * @param HolidayProcessingService $holidayProcessingService
     * @param VacationTimeProcessAttendanceService $vacationTimeProcessAttendanceService
     * @param AttendanceTimeProcessorService $attendanceTimeProcessorService
     */
    public function __construct(
        HolidayProcessingService $holidayProcessingService,
        VacationTimeProcessAttendanceService $vacationTimeProcessAttendanceService,
        AttendanceTimeProcessorService $attendanceTimeProcessorService
    ) {
        Log::info("Initializing AttendanceProcessingService...");
        $this->holidayProcessingService = $holidayProcessingService;
        $this->vacationTimeProcessAttendanceService = $vacationTimeProcessAttendanceService;
        $this->attendanceTimeProcessorService = $attendanceTimeProcessorService;
    }

    /**
     * Process all attendance records for the given PayPeriod.
     *
     * @param PayPeriod $payPeriod
     * @return void
     */
    public function processAll(PayPeriod $payPeriod): void
    {
        Log::info("Starting Attendance Processing for PayPeriod ID: {$payPeriod->id}");

        // Step 1: Process Vacation Records
        Log::info("Launching VacationTimeProcessAttendanceService...");
        $this->vacationTimeProcessAttendanceService->processVacationDays(
            $payPeriod->start_date->format('Y-m-d'),
            $payPeriod->end_date->format('Y-m-d')
        );
        Log::info("VacationTimeProcessAttendanceService completed for PayPeriod ID: {$payPeriod->id}");

        // Step 2: Process Holiday Records
        Log::info("Launching HolidayProcessingService...");
        $this->holidayProcessingService->processHolidays(
            $payPeriod->start_date->format('Y-m-d'),
            $payPeriod->end_date->format('Y-m-d')
        );
        Log::info("HolidayProcessingService completed for PayPeriod ID: {$payPeriod->id}");

        // Step 3: Process Regular Attendance Records
        Log::info("Launching AttendanceTimeProcessorService...");
        $this->attendanceTimeProcessorService->processAttendanceForPayPeriod($payPeriod);
        Log::info("AttendanceTimeProcessorService completed for PayPeriod ID: {$payPeriod->id}");

        Log::info("Attendance Processing completed for PayPeriod ID: {$payPeriod->id}");
    }
}
