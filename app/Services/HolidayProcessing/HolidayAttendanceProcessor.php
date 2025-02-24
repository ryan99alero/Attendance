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
        Log::info("[HolidayAttendanceProcessor] 🔍 Starting Holiday Processing for PayPeriod ID: {$payPeriod->id}");

        // ✅ Ensure holidays are processed only once per pay period
        $this->holidayProcessingService->processHolidaysForPayPeriod($payPeriod);

        Log::info("[HolidayAttendanceProcessor] ✅ Successfully Completed Holiday Processing for PayPeriod ID: {$payPeriod->id}");
    }
}
