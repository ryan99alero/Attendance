<?php

use App\Http\Controllers\PayrollExportController;
use App\Http\Controllers\ReportsController;
use App\Http\Livewire\Controllers\AttendanceController;
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

// Payroll export routes
Route::prefix('payroll')->name('payroll.')->middleware('auth')->group(function () {
    Route::get('/export/{export}/download', [PayrollExportController::class, 'download'])->name('export.download');
    Route::delete('/export/{export}', [PayrollExportController::class, 'destroy'])->name('export.destroy');
    Route::get('/exports', [PayrollExportController::class, 'index'])->name('exports.index');
});
