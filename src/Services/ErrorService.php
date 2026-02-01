<?php

namespace Warp\LaravelAiCodeOrchestrator\Services;

use Illuminate\Support\Facades\Log;
use Throwable;
use Warp\LaravelAiCodeOrchestrator\Clients\AiClientInterface;
use Warp\LaravelAiCodeOrchestrator\Jobs\SendErrorSolutionJob;
use Warp\LaravelAiCodeOrchestrator\Models\ErrorReport;
use Warp\LaravelAiCodeOrchestrator\Support\CodeContextBuilder;

class ErrorService
{
    public function __construct(private AiClientInterface $aiClient)
    {
    }

    public function handleThrowable(Throwable $throwable, array $context = []): void
    {
        if (! config('ai-code-orchestrator.enabled', true)) {
            return;
        }

        $context = $this->augmentContext($throwable, $context);
        $report = $this->storeReport($throwable, $context);

        if (! $report) {
            return;
        }

        try {
            $solution = $this->aiClient->analyze($throwable, $context);
            $solution = is_string($solution) && $solution !== '' ? $solution : 'AI non ha fornito una risposta valida.';
            $report->ai_solution = $solution;
            $report->status = $solution === 'AI non ha fornito una risposta valida.' ? 'ai_empty' : 'analyzed';
            $report->save();
        } catch (Throwable $aiError) {
            $report->ai_solution = 'AI non disponibile: '.$aiError->getMessage();
            $report->status = 'ai_failed';
            $report->save();
            Log::warning('AI analysis failed', ['error' => $aiError->getMessage()]);
        }

        SendErrorSolutionJob::dispatch($report->id)
            ->onQueue(config('ai-code-orchestrator.queue', 'default'));
    }

    private function augmentContext(Throwable $throwable, array $context): array
    {
        $builder = new CodeContextBuilder();
        $snippetLines = (int) config('ai-code-orchestrator.ai.context.snippet_lines', 30);
        $depth = (int) config('ai-code-orchestrator.ai.context.depth', 1);
        $maxChars = (int) config('ai-code-orchestrator.ai.context.max_chars', 6000);
        $maxFrames = (int) config('ai-code-orchestrator.ai.context.max_frames', 4);
        $maxBlockLines = (int) config('ai-code-orchestrator.ai.context.max_block_lines', 20);
        $stripComments = (bool) config('ai-code-orchestrator.ai.context.strip_comments', true);
        $excludeGlobs = (array) config('ai-code-orchestrator.ai.context.exclude_globs', []);

        return array_merge($context, $builder->build($throwable, $snippetLines, $depth, $maxChars, $maxFrames, $maxBlockLines, $stripComments, $excludeGlobs));
    }

    private function storeReport(Throwable $throwable, array $context): ?ErrorReport
    {
        if (! config('ai-code-orchestrator.store_errors', true)) {
            return null;
        }

        return ErrorReport::create([
            'exception_class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
            'url' => $context['url'] ?? null,
            'method' => $context['method'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'context' => $context,
            'status' => 'pending',
        ]);
    }
}
