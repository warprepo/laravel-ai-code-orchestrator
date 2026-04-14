<?php

namespace Warp\LaravelAiCodeOrchestrator\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Warp\LaravelAiCodeOrchestrator\Models\ErrorReport;
use Warp\LaravelAiCodeOrchestrator\Services\AiSolutionApplyService;
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

    public function applySolution(Request $request, int $report, AiSolutionApplyService $applyService)
    {
        if ($this->isProductionEnv()) {
            return response('Operazione non consentita in produzione.', 403);
        }

        $token = (string) config('ai-code-orchestrator.manual_report_token', '');
        $incomingToken = (string) $request->query('token', '');

        if ($token === '' || ! hash_equals($token, $incomingToken)) {
            return response('Unauthorized', 401);
        }

        $errorReport = ErrorReport::findOrFail($report);
        $result = $applyService->apply($errorReport);
        $status = $result['ok'] ? 200 : 422;

        return response()->json([
            'report_id' => $errorReport->id,
            'status' => $errorReport->status,
            'result' => $result,
        ], $status);
    }

    private function isProductionEnv(): bool
    {
        $env = strtolower((string) app()->environment());

        return Str::contains($env, 'production');
    }
}
