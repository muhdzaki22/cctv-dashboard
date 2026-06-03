<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// API routes for dashboard data
Route::prefix('api/dashboard')->group(function () {
    Route::get('/hourly', [DashboardController::class, 'hourly'])->name('api.hourly');
    Route::get('/daily', [DashboardController::class, 'daily'])->name('api.daily');
    Route::get('/weekly', [DashboardController::class, 'weekly'])->name('api.weekly');
    Route::get('/gender-stats', [DashboardController::class, 'genderStats'])->name('api.gender');
    Route::get('/peak-hour', [DashboardController::class, 'peakHour'])->name('api.peak');
});
