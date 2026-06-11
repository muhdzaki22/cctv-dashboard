<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NvrRecordingController;
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

// API routes for NVR recordings
Route::prefix('api/recordings')->group(function () {
    Route::post('/authenticate', [NvrRecordingController::class, 'authenticate'])->name('api.recordings.authenticate');
    Route::post('/fetch', [NvrRecordingController::class, 'fetchAndStore'])->name('api.recordings.fetch');
    Route::get('/hourly', [NvrRecordingController::class, 'hourly'])->name('api.recordings.hourly');
    Route::get('/daily', [NvrRecordingController::class, 'daily'])->name('api.recordings.daily');
    Route::get('/weekly', [NvrRecordingController::class, 'weekly'])->name('api.recordings.weekly');
    Route::get('/stats', [NvrRecordingController::class, 'stats'])->name('api.recordings.stats');
    Route::get('/duration-categories', [NvrRecordingController::class, 'durationCategories'])->name('api.recordings.duration-categories');
    Route::get('/peak-hour', [NvrRecordingController::class, 'peakHour'])->name('api.recordings.peak-hour');
});
