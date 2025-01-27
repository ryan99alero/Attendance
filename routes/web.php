<?php

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

// Route for punches
//Route::get('/admin/punches', [PunchController::class, 'index'])->name('punches.index');

//use App\Http\Livewire\CreateTimeRecordModal;
//
//Livewire::component('create-time-record-modal', CreateTimeRecordModal::class);
