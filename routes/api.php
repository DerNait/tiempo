<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\RainmeterController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\WeeklyReviewController;
use Illuminate\Support\Facades\Route;

/*
 * Session-authenticated JSON API for the SPA.
 */
Route::middleware('web')->post('/login', [AuthController::class, 'login'])->name('api.login');

/*
 * The `web` group gives these routes the session and CSRF protection the SPA
 * relies on. `time:write` is granted implicitly to session-authenticated
 * users, so the group is reachable from the SPA but never from a read-only
 * Rainmeter token.
 */
Route::middleware(['web', 'auth:sanctum', 'abilities:time:write'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/status', [TrackingController::class, 'show']);
    Route::post('/tracking/start', [TrackingController::class, 'start']);
    Route::post('/tracking/stop', [TrackingController::class, 'stop']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::post('/categories/reorder', [CategoryController::class, 'reorder']);
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::get('/time-entries', [TimeEntryController::class, 'index']);
    Route::get('/time-entries/export', [TimeEntryController::class, 'export']);
    Route::post('/time-entries', [TimeEntryController::class, 'store']);
    Route::patch('/time-entries/{timeEntry}', [TimeEntryController::class, 'update']);
    Route::delete('/time-entries/{timeEntry}', [TimeEntryController::class, 'destroy']);

    Route::get('/reports/day', [ReportController::class, 'day']);
    Route::get('/reports/week', [ReportController::class, 'week']);

    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::post('/budgets', [BudgetController::class, 'store']);
    Route::post('/budgets/copy-previous', [BudgetController::class, 'copyPrevious']);
    Route::post('/budgets/apply-template', [BudgetController::class, 'applyTemplate']);

    Route::get('/weekly-reviews', [WeeklyReviewController::class, 'index']);
    Route::get('/weekly-review', [WeeklyReviewController::class, 'show']);
    Route::post('/weekly-review', [WeeklyReviewController::class, 'store']);

    Route::get('/settings', [SettingsController::class, 'show']);
    Route::patch('/settings', [SettingsController::class, 'update']);

    Route::get('/tokens', [ApiTokenController::class, 'index']);
    Route::post('/tokens', [ApiTokenController::class, 'store']);
    Route::delete('/tokens/{token}', [ApiTokenController::class, 'destroy']);
});

/*
 * Read-only plain text endpoint for the Rainmeter skin. The `time:read`
 * ability is mandatory, so this token can never mutate tracking data.
 */
Route::middleware(['auth:sanctum', 'abilities:time:read', 'throttle:rainmeter'])
    ->get('/rainmeter/status', RainmeterController::class)
    ->name('rainmeter.status');
