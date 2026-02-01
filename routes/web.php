<?php

use Illuminate\Support\Facades\Route;
use Warp\LaravelAiCodeOrchestrator\Controllers\ErrorController;

Route::prefix('ai-code-orchestrator')->group(function () {
    Route::post('/report-error', [ErrorController::class, 'reportError']);
});
