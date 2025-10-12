<?php

use App\Http\Livewire\Controllers\AttendanceController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;


// Route for the homepage
Route::get('/', function () {
    return view('welcome');
});

// Route for the Attendance
Route::get('/api/hours-worked', [AttendanceController::class, 'getHoursWorked']);
Route::get('/api/time-off', [AttendanceController::class, 'getTimeOff']);
Route::get('/api/pay-period-summary', [AttendanceController::class, 'getPayPeriodSummary']);
Route::post('/api/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

// Reports routes
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/dashboard', [ReportsController::class, 'dashboard'])->name('dashboard');
    Route::get('/sample', [ReportsController::class, 'sample'])->name('sample');
    Route::post('/adp-export', [ReportsController::class, 'generateADPExport'])->name('adp-export');
    Route::get('/configuration', [ReportsController::class, 'getConfiguration'])->name('configuration');
});
