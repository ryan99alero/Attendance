<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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