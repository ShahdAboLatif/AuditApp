<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\CameraFormController;
use App\Http\Controllers\Api\CameraReportController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\Cleaning\CleaningDueController;
use App\Http\Controllers\Api\Cleaning\CleaningReportController;
use App\Http\Controllers\Api\Cleaning\CleaningTaskController;
use App\Http\Controllers\Api\Cleaning\EvaluationController;
use App\Http\Controllers\Api\Cleaning\InspectionItemController;
use App\Http\Controllers\Api\CustomReportController;
use App\Http\Controllers\Api\EntityController;
use App\Http\Middleware\AuthTokenStoreScopeMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([
    AuthTokenStoreScopeMiddleware::class,
])->group(function () {

    Route::get('/audits/ratings-summary/{store_id}/{date_start}/{date_end}', [AuditController::class, 'ratingsSummary']);

    Route::apiResource('camera-forms', CameraFormController::class)->except('update');
    Route::post('camera-forms/{camera_form}', [CameraFormController::class, 'update'])->name('camera-forms.update');
    Route::get('camera-reports', [CameraReportController::class, 'index']);
    Route::get('camera-reports/export', [CameraReportController::class, 'export']);
    // ================================= export functions ==============================
    Route::get('camera-reports/exportExcel', [CameraReportController::class, 'exportExcel'])->name('camera-reports.exportExcel');
    Route::get('camera-reports/exportImages', [CameraReportController::class, 'exportImages'])->name('camera-reports.exportImages');

    // ================================= Custom Reports ==============================
    Route::apiResource('custom-reports', CustomReportController::class);
    // ==================================

    Route::apiResource('audits', AuditController::class)
        ->only(['index', 'show']);
    // Route::get('audits/summary/{store_code}/{date}', [AuditController::class, 'summary']);

    Route::apiResource('entities', EntityController::class)
        ->except(['show', 'create', 'edit']);

    Route::apiResource('categories', CategoryController::class)
        ->only(['store', 'update', 'destroy']);

    // ================================= Cleaning Chart =============================
    Route::prefix('cleaning')->group(function () {
        // Track 1 — Tasks (auditor/super create; list/show)
        Route::get('tasks', [CleaningTaskController::class, 'index'])->name('cleaning.tasks.index');
        Route::post('tasks', [CleaningTaskController::class, 'store'])->name('cleaning.tasks.store');
        Route::get('tasks/{task}', [CleaningTaskController::class, 'show'])->name('cleaning.tasks.show');

        // Store-scoped "what's due" — computed on read. Store id in the PATH (must be {store_id}).
        Route::get('stores/{store_id}/dates/{date}/due', [CleaningDueController::class, 'dueOnDate'])->name('cleaning.due.date');
        Route::get('stores/{store_id}/due-range', [CleaningDueController::class, 'dueRange'])->name('cleaning.due.range');

        // Complete / undo a task for a store on a date; per-task history.
        Route::post('stores/{store_id}/tasks/{task}/complete', [CleaningDueController::class, 'complete'])->name('cleaning.tasks.complete');
        Route::post('stores/{store_id}/tasks/{task}/uncomplete', [CleaningDueController::class, 'uncomplete'])->name('cleaning.tasks.uncomplete');
        Route::get('stores/{store_id}/tasks/{task}/history', [CleaningDueController::class, 'history'])->name('cleaning.tasks.history');

        // Track 2 — Evaluation (auditor/super)
        Route::get('evaluations', [EvaluationController::class, 'index'])->name('cleaning.evaluations.index');
        Route::post('evaluations', [EvaluationController::class, 'upsert'])->name('cleaning.evaluations.upsert');
        Route::post('evaluations/finalize', [EvaluationController::class, 'finalize'])->name('cleaning.evaluations.finalize');

        Route::get('inspection-items', [InspectionItemController::class, 'index'])->name('cleaning.items.index');
        Route::post('inspection-items', [InspectionItemController::class, 'store'])->name('cleaning.items.store');
        Route::delete('inspection-items/{inspection_item}', [InspectionItemController::class, 'destroy'])->name('cleaning.items.destroy');

        // Reports
        Route::get('reports/csv', [CleaningReportController::class, 'csv'])->name('cleaning.reports.csv');
        Route::get('reports/data', [CleaningReportController::class, 'data'])->name('cleaning.reports.data');
    });
});
