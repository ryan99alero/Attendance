<?php

use App\Jobs\CheckDeviceOfflineStatus;
use App\Models\CompanySetup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// =====================================================
// CLOCK EVENT PROCESSING - Based on Company Setup setting
// =====================================================
// Reads clock_event_sync_frequency from CompanySetup and schedules accordingly
$clockEventFrequency = null;
try {
    $clockEventFrequency = CompanySetup::first()?->clock_event_sync_frequency;
} catch (\Exception $e) {
    // Database may not be ready during initial setup
}

if ($clockEventFrequency && $clockEventFrequency !== 'manual_only' && $clockEventFrequency !== 'real_time') {
    $clockEventSchedule = Schedule::command('clock-events:process')
        ->withoutOverlapping()
        ->runInBackground()
        ->sendOutputTo(storage_path('logs/clock-events.log'));

    switch ($clockEventFrequency) {
        case 'every_minute':
            $clockEventSchedule->everyMinute();
            break;
        case 'every_5_minutes':
            $clockEventSchedule->everyFiveMinutes();
            break;
        case 'every_15_minutes':
            $clockEventSchedule->everyFifteenMinutes();
            break;
        case 'every_30_minutes':
            $clockEventSchedule->everyThirtyMinutes();
            break;
        case 'hourly':
            $clockEventSchedule->hourly();
            break;
        case 'twice_daily':
            $clockEventSchedule->twiceDaily(8, 18); // 8am and 6pm
            break;
        case 'daily':
            $clockEventSchedule->dailyAt('06:00');
            break;
    }
}

// =====================================================
// PAYROLL & HR PROCESSING
// =====================================================

// PayPeriod Generation - Daily at 2:00 AM (1 month ahead)
Schedule::command('payroll:generate-periods --months=1')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Vacation Accrual Processing - Daily at 3:00 AM
Schedule::command('vacation:process-accruals')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->sendOutputTo(storage_path('logs/vacation-accruals.log'))
    ->emailOutputOnFailure(env('ADMIN_EMAIL'));

// Holiday Auto-Creation - Yearly on December 1st at 4:00 AM (create next year's holidays)
Schedule::command('holidays:create-upcoming')
    ->yearlyOn(12, 1, '04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->sendOutputTo(storage_path('logs/holiday-creation.log'))
    ->emailOutputOnFailure(env('ADMIN_EMAIL'));

// =====================================================
// DEVICE MONITORING
// =====================================================

// Device Offline Status Check - Every minute
// Checks all time clock devices and sends email alerts when offline/back online
Schedule::job(new CheckDeviceOfflineStatus())
    ->everyMinute()
    ->withoutOverlapping();
