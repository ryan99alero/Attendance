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
