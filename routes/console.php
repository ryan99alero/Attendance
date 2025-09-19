<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

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
