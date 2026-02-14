<?php

namespace App\Services\HolidayProcessing;

use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;

class HolidayAttendanceProcessor
{
    protected HolidayAttendanceService $holidayAttendanceService;

    public function __construct(HolidayAttendanceService $holidayAttendanceService)
    {
        $this->holidayAttendanceService = $holidayAttendanceService;
    }

    /**
     * Process Holidays for the Pay Period using HolidayInstance records.
     */
    public function processHolidaysForPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("[HolidayAttendanceProcessor] Starting Holiday Processing for PayPeriod ID: {$payPeriod->id}");

        $processed = $this->holidayAttendanceService->processHolidaysForPayPeriod($payPeriod);

        Log::info("[HolidayAttendanceProcessor] Completed - {$processed} employee holiday records created for PayPeriod ID: {$payPeriod->id}");
    }
}
