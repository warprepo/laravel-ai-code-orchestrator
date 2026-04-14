<?php

use Illuminate\Support\Facades\Route;
use Warp\LaravelAiCodeOrchestrator\Controllers\ErrorController;

Route::prefix('ai-code-orchestrator')->group(function () {
    Route::post('/report-error', [ErrorController::class, 'reportError']);
    Route::get('/apply-solution/{report}', [ErrorController::class, 'applySolution'])
        ->name('ai-code-orchestrator.apply-solution');
});
