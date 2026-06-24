<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NvrRecordingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// API routes for dashboard data
Route::prefix('api/dashboard')->group(function () {
    Route::get('/hourly', [DashboardController::class, 'hourly']);
    Route::get('/daily', [DashboardController::class, 'daily']);
    Route::get('/weekly', [DashboardController::class, 'weekly']);
    Route::get('/gender-stats', [DashboardController::class, 'genderStats']);
    Route::get('/peak-hour', [DashboardController::class, 'peakHour']);
});

// API routes for NVR recordings
Route::prefix('api/recordings')->group(function () {
    Route::post('/authenticate', [NvrRecordingController::class, 'authenticate'])->name('api.recordings.authenticate');
    Route::post('/fetch', [NvrRecordingController::class, 'fetchAndStore'])->name('api.recordings.fetch');
    Route::get('/hourly', [NvrRecordingController::class, 'hourly']);
    Route::get('/daily', [NvrRecordingController::class, 'daily']);
    Route::get('/weekly', [NvrRecordingController::class, 'weekly']);
    Route::get('/stats', [NvrRecordingController::class, 'stats']);
    Route::get('/duration-categories', [NvrRecordingController::class, 'durationCategories']);
    Route::get('/peak-hour', [NvrRecordingController::class, 'peakHour']);
});

require __DIR__.'/auth.php';