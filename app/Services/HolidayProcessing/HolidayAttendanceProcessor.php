<?php

namespace App\Services\HolidayProcessing;

use App\Models\PayPeriod;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HolidayAttendanceProcessor
{
    protected HolidayProcessingService $holidayProcessingService;

    public function __construct(HolidayProcessingService $holidayProcessingService)
    {
        $this->holidayProcessingService = $holidayProcessingService;
    }

    /**
     * ✅ Process Holidays ONLY for the Pay Period
     */
    public function processHolidaysForPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("🔍 [HolidayAttendanceProcessor] Processing Holidays for PayPeriod ID: {$payPeriod->id}");

        // ✅ Call `processHolidaysForPayPeriod` instead of `processHolidayForEmployees`
        $this->holidayProcessingService->processHolidaysForPayPeriod($payPeriod);

        Log::info("✅ [HolidayAttendanceProcessor] Completed Holiday Processing.");
    }
}
