<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PunchController;

// Route for the homepage
Route::get('/', function () {
    return view('welcome');
});

// Route for punches
Route::get('/admin/punches', [PunchController::class, 'index'])->name('punches.index');
