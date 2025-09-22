<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TimeClockController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Keep-alive endpoint for long-running operations (no auth required)
Route::post('/keep-alive', function (Request $request) {
    return response()->json([
        'status' => 'alive',
        'timestamp' => now(),
        'processing' => $request->input('processing', false)
    ]);
})->name('api.keep-alive');

// Keep-alive endpoint with web middleware for authenticated sessions
Route::middleware('web')->post('/web-keep-alive', function (Request $request) {
    return response()->json([
        'status' => 'alive',
        'timestamp' => now(),
        'session_id' => session()->getId(),
        'processing' => $request->input('processing', false)
    ]);
})->name('web.keep-alive');

/*
|--------------------------------------------------------------------------
| ESP32 Time Clock API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1/timeclock')->group(function () {
    // Public health check
    Route::get('/health', [TimeClockController::class, 'health'])
        ->name('timeclock.health');

    // Device registration (no auth required for initial registration)
    Route::post('/register', [TimeClockController::class, 'register'])
        ->name('timeclock.register');

    // Device status check (requires device authentication)
    Route::get('/status', [TimeClockController::class, 'status'])
        ->name('timeclock.status');

    // Device authentication and handshake
    Route::post('/auth', [TimeClockController::class, 'authenticate'])
        ->name('timeclock.auth');

    // Record punch from card/RFID swipe
    Route::post('/punch', [TimeClockController::class, 'recordPunch'])
        ->name('timeclock.punch');

    // Configuration sync endpoints
    Route::get('/config', [TimeClockController::class, 'getConfig'])
        ->name('timeclock.config.get');

    Route::put('/config/{deviceId}', [TimeClockController::class, 'updateConfig'])
        ->name('timeclock.config.update');

    // Get employee information and hours
    Route::get('/employee/{card_id}', [TimeClockController::class, 'getEmployeeInfo'])
        ->name('timeclock.employee.info');
});