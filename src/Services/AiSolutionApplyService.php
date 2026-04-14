<?php

namespace Warp\LaravelAiCodeOrchestrator\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;
use Warp\LaravelAiCodeOrchestrator\Models\ErrorReport;

class AiSolutionApplyService
{
    private const int DEFAULT_TIMEOUT_SECONDS = 180;

    public function apply(ErrorReport $report): array
    {
        $gitSetup = $this->prepareFixBranch($report);
        if (! $gitSetup['ok']) {
            return [
                'ok' => false,
                'message' => $gitSetup['message'],
            ];
        }

        $branchName = $gitSetup['branch'];
        $solution = (string) ($report->ai_solution ?? '');
        $storedPatch = $this->extractPatch($solution);
        $fallbackError = null;

        $patch = null;
        $patchSource = null;
        $storedError = null;

        if ($storedPatch !== null) {
            $storedPatch = $this->normalizePatch($storedPatch);
            $storedValidation = $this->validatePatch($storedPatch, $report->id);

            if ($storedValidation['ok']) {
                $patch = $storedPatch;
                $patchSource = 'stored_solution';
            } else {
                $storedError = $storedValidation['error'];
            }
        } else {
            $storedError = 'Nessuna patch trovata nella risposta AI.';
        }

        if ($patch === null) {
            $fallbackPatch = $this->generatePatchWithLlama($report);
            if ($fallbackPatch !== null) {
                $fallbackPatch = $this->normalizePatch($fallbackPatch);
                $fallbackValidation = $this->validatePatch($fallbackPatch, $report->id);
                if ($fallbackValidation['ok']) {
                    $patch = $fallbackPatch;
                    $patchSource = 'llama_fallback';
                } else {
                    $fallbackError = $fallbackValidation['error'];
                }
            } else {
                $fallbackError = 'Fallback patch-only non ha restituito una patch valida.';
            }
        }

        if ($patch === null) {
            return [
                'ok' => false,
                'message' => 'Impossibile ottenere una patch valida.',
                'stored_error' => $storedError,
                'fallback_error' => $fallbackError,
                'branch' => $branchName,
            ];
        }

        $patchDir = storage_path('app/ai-code-orchestrator/patches');
        File::ensureDirectoryExists($patchDir);

        $patchFile = $patchDir.'/report-'.$report->id.'-'.date('Ymd_His').'.patch';
        File::put($patchFile, $patch);

        $applyProcess = new Process([
            'git',
            '-C',
            base_path(),
            'apply',
            '--reject',
            '--whitespace=nowarn',
            $patchFile,
        ]);
        $applyProcess->run();

        if (! $applyProcess->isSuccessful()) {
            return [
                'ok' => false,
                'message' => 'Patch non applicata: '.$applyProcess->getErrorOutput(),
                'patch_file' => $patchFile,
                'patch_source' => $patchSource,
                'branch' => $branchName,
            ];
        }

        $commitResult = $this->commitAndPushFix($report, $branchName);
        if (! $commitResult['ok']) {
            return [
                'ok' => false,
                'message' => $commitResult['message'],
                'patch_file' => $patchFile,
                'patch_source' => $patchSource,
                'branch' => $branchName,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Patch applicata correttamente.',
            'patch_file' => $patchFile,
            'patch_source' => $patchSource,
            'branch' => $branchName,
            'commit' => $commitResult['commit'],
        ];
    }

    private function validatePatch(string $patch, int $reportId): array
    {
        $patchDir = storage_path('app/ai-code-orchestrator/patches');
        File::ensureDirectoryExists($patchDir);
        $tmpFile = $patchDir.'/report-'.$reportId.'-validation-'.date('Ymd_His').'-'.bin2hex(random_bytes(3)).'.patch';
        File::put($tmpFile, $patch);

        $result = $this->runGitApplyCheck($tmpFile);

        return $result;
    }

    private function prepareFixBranch(ErrorReport $report): array
    {
        $repoPath = base_path();
        $branchName = $this->buildFixBranchName($report);

        $checkout = new Process([
            'git',
            '-C',
            $repoPath,
            'checkout',
            '-b',
            $branchName,
        ]);
        $checkout->run();

        if (! $checkout->isSuccessful()) {
            return [
                'ok' => false,
                'message' => 'Impossibile creare il branch fix: '.$checkout->getErrorOutput(),
            ];
        }

        $push = new Process([
            'git',
            '-C',
            $repoPath,
            'push',
            '-u',
            'origin',
            $branchName,
        ]);
        $push->run();

        if (! $push->isSuccessful()) {
            return [
                'ok' => false,
                'message' => 'Branch creato ma push iniziale fallito: '.$push->getErrorOutput(),
                'branch' => $branchName,
            ];
        }

        return [
            'ok' => true,
            'branch' => $branchName,
        ];
    }

    private function commitAndPushFix(ErrorReport $report, string $branchName): array
    {
        $repoPath = base_path();

        $add = new Process(['git', '-C', $repoPath, 'add', '-A']);
        $add->run();
        if (! $add->isSuccessful()) {
            return [
                'ok' => false,
                'message' => 'Patch applicata ma git add fallito: '.$add->getErrorOutput(),
            ];
        }

        $commitMessage = "AI fix for report #{$report->id}";
        $commit = new Process([
            'git',
            '-C',
            $repoPath,
            '-c',
            'commit.gpgsign=false',
            'commit',
            '-m',
            $commitMessage,
        ]);
        $commit->run();

        if (! $commit->isSuccessful()) {
            return [
                'ok' => false,
                'message' => 'Patch applicata ma commit fallito: '.$commit->getErrorOutput(),
            ];
        }

        $push = new Process([
            'git',
            '-C',
            $repoPath,
            'push',
            'origin',
            $branchName,
        ]);
        $push->run();

        if (! $push->isSuccessful()) {
            return [
                'ok' => false,
                'message' => 'Commit creato ma push fallito: '.$push->getErrorOutput(),
            ];
        }

        $hash = new Process([
            'git',
            '-C',
            $repoPath,
            'rev-parse',
            '--short',
            'HEAD',
        ]);
        $hash->run();

        return [
            'ok' => true,
            'commit' => trim($hash->getOutput()),
        ];
    }

    private function buildFixBranchName(ErrorReport $report): string
    {
        $suffix = date('YmdHis').'-'.$report->id;

        return 'ai-fix/report-'.$suffix;
    }

    private function runGitApplyCheck(string $patchFile): array
    {
        $process = new Process([
            'git',
            '-C',
            base_path(),
            'apply',
            '--check',
            $patchFile,
        ]);
        $process->run();

        if ($process->isSuccessful()) {
            return ['ok' => true, 'error' => null];
        }

        return [
            'ok' => false,
            'error' => trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : trim($process->getOutput()),
        ];
    }

    private function generatePatchWithLlama(ErrorReport $report): ?string
    {
        $config = (array) config('ai-code-orchestrator.ai.llama', []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $model = (string) ($config['model'] ?? 'local-model');
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($baseUrl === '') {
            return null;
        }

        $timeout = (int) config('ai-code-orchestrator.ai.timeout', self::DEFAULT_TIMEOUT_SECONDS);
        $timeout = max(10, $timeout);

        $prompt = $this->buildPatchOnlyPrompt($report);
        $maxTokenAttempts = [900, 1400, 1800];

        foreach ($maxTokenAttempts as $maxTokens) {
            if ($this->shouldLogDebug()) {
                Log::info('ai_orchestrator.apply.patch_fallback.start', [
                    'report_id' => $report->id,
                    'max_tokens' => $maxTokens,
                    'model' => $model,
                    'timeout_seconds' => $timeout,
                ]);
            }

            $payload = [
                'model' => $model,
                'temperature' => 0.1,
                'max_tokens' => $maxTokens,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Return only one fenced ```patch``` block with a complete unified diff. Never use placeholders like "... [truncated]". Do not add explanations.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ];

            $client = Http::asJson();
            if ($apiKey !== '') {
                $client = $client->withToken($apiKey);
            }

            try {
                $response = $client
                    ->timeout($timeout)
                    ->post($baseUrl.'/chat/completions', $payload);
            } catch (Throwable $e) {
                if ($this->shouldLogDebug()) {
                    Log::warning('ai_orchestrator.apply.patch_fallback.exception', [
                        'report_id' => $report->id,
                        'max_tokens' => $maxTokens,
                        'error_class' => $e::class,
                        'error' => $e->getMessage(),
                    ]);
                }

                continue;
            }

            if (! $response->successful()) {
                if ($this->shouldLogDebug()) {
                    Log::warning('ai_orchestrator.apply.patch_fallback.failed', [
                        'report_id' => $report->id,
                        'max_tokens' => $maxTokens,
                        'status' => $response->status(),
                        'body_preview' => mb_substr($response->body(), 0, 1000),
                    ]);
                }

                continue;
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $patch = $this->extractPatch($content);
            $json = $response->json();

            if ($patch === null || $this->hasTruncationMarker($patch)) {
                if ($this->shouldLogDebug()) {
                    Log::warning('ai_orchestrator.apply.patch_fallback.truncated_or_empty', [
                        'report_id' => $report->id,
                        'max_tokens' => $maxTokens,
                        'response_chars' => mb_strlen($content),
                        'finish_reason' => data_get($json, 'choices.0.finish_reason'),
                        'usage_prompt_tokens' => data_get($json, 'usage.prompt_tokens'),
                        'usage_completion_tokens' => data_get($json, 'usage.completion_tokens'),
                        'usage_total_tokens' => data_get($json, 'usage.total_tokens'),
                        'content_preview' => mb_substr($content, 0, 2000),
                    ]);
                }

                continue;
            }

            if ($this->shouldLogDebug()) {
                Log::info('ai_orchestrator.apply.patch_fallback.success', [
                    'report_id' => $report->id,
                    'max_tokens' => $maxTokens,
                    'response_chars' => mb_strlen($content),
                    'finish_reason' => data_get($json, 'choices.0.finish_reason'),
                    'usage_prompt_tokens' => data_get($json, 'usage.prompt_tokens'),
                    'usage_completion_tokens' => data_get($json, 'usage.completion_tokens'),
                    'usage_total_tokens' => data_get($json, 'usage.total_tokens'),
                    'content_preview' => mb_substr($content, 0, 2000),
                ]);
            }

            return $patch;
        }

        return null;
    }

    private function buildPatchOnlyPrompt(ErrorReport $report): string
    {
        $context = is_array($report->context ?? null) ? $report->context : [];
        $trace = (string) ($context['filtered_trace'] ?? $report->trace ?? '');
        $codeContext = (string) ($context['code_context'] ?? '');
        $offendingLine = (string) ($context['offending_line'] ?? '');

        return "Generate a unified diff patch for this Laravel issue.\n".
            "Rules:\n".
            "- Output only one fenced block: ```patch ... ```\n".
            "- Include valid file headers (---/+++), and @@ hunks.\n".
            "- Modify only project files under app/, config/, packages/, resources/.\n".
            "- Use exact existing file paths from the project; do not invent or shorten package paths.\n".
            "- Keep patch minimal and safe.\n\n".
            "Error message: {$report->message}\n".
            "Exception class: {$report->exception_class}\n".
            "File: {$report->file}:{$report->line}\n".
            "Offending line: {$offendingLine}\n".
            "Trace:\n{$trace}\n\n".
            ($codeContext !== '' ? "Code context:\n{$codeContext}\n\n" : '').
            "Do not output analysis. Output only one complete ```patch``` block.";
    }

    private function extractPatch(string $solution): ?string
    {
        $decoded = html_entity_decode($solution, ENT_QUOTES | ENT_HTML5);
        $decoded = preg_replace('/<br\\s*\\/?>/i', "\n", $decoded) ?? $decoded;
        $decoded = preg_replace('/<\/p>/i', "\n", $decoded) ?? $decoded;
        $decoded = strip_tags($decoded);
        $decoded = str_replace(["\r\n", "\r"], "\n", $decoded);

        if (preg_match('/```(?:patch|diff)\s*(.*?)```/is', $decoded, $matches) === 1) {
            $patch = trim($matches[1]);
            return $patch !== '' ? $patch."\n" : null;
        }

        if (preg_match('/(\*\*\* Begin Patch.*\*\*\* End Patch)/is', $decoded, $matches) === 1) {
            $patch = trim($matches[1]);
            return $patch !== '' ? $patch."\n" : null;
        }

        if (preg_match('/(diff --git .*?$)/is', $decoded, $matches) === 1) {
            $patch = trim($matches[1]);
            return $patch !== '' ? $patch."\n" : null;
        }

        return null;
    }

    private function normalizePatch(string $patch): string
    {
        $patch = str_replace(["\r\n", "\r"], "\n", $patch);
        $patch = preg_replace('/^\xEF\xBB\xBF/', '', $patch) ?? $patch;
        $patch = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $patch) ?? $patch;
        $patch = preg_replace('/^```(?:patch|diff)?\s*/i', '', $patch) ?? $patch;
        $patch = preg_replace('/```$/', '', $patch) ?? $patch;
        $patch = str_replace('packages/laravel-ai-code-orchestrator/', 'packages/warp/laravel-ai-code-orchestrator/', $patch);
        $patch = ltrim($patch, "\n");

        return rtrim($patch)."\n";
    }

    private function hasTruncationMarker(string $patch): bool
    {
        $haystack = strtolower($patch);

        return str_contains($haystack, '... [truncated]') || str_contains($haystack, '[truncated]');
    }

    private function shouldLogDebug(): bool
    {
        return (bool) config('app.debug', false);
    }
}
