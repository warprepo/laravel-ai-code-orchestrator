<?php

namespace Warp\LaravelAiCodeOrchestrator\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Warp\LaravelAiCodeOrchestrator\Services\ErrorService;

class ErrorController extends Controller
{
    protected $errorService;

    public function __construct(ErrorService $errorService)
    {
        $this->errorService = $errorService;
    }

    public function reportError(Request $request)
    {
        if (! config('ai-code-orchestrator.allow_manual_reports', false)) {
            abort(404);
        }

        $token = config('ai-code-orchestrator.manual_report_token');

        if ($token && $request->header('X-AI-Report-Token') !== $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $message = (string) $request->input('message', 'Errore manuale');
        $throwable = new \RuntimeException($message);
        $context = $request->only(['url', 'method', 'user_id']);

        $this->errorService->handleThrowable($throwable, $context);

        return response()->json(['status' => 'ok']);
    }
}
