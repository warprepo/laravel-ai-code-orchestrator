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
        $patch = $this->extractPatch($solution);
        $patchSource = 'stored_solution';

        if ($patch === null) {
            $patch = $this->generatePatchWithLlama($report);
            $patchSource = 'llama_fallback';
        }

        if ($patch === null) {
            return [
                'ok' => false,
                'message' => 'Nessuna patch trovata nella risposta AI e fallback Llama non riuscito.',
                'branch' => $branchName,
            ];
        }

        $patchDir = storage_path('app/ai-code-orchestrator/patches');
        File::ensureDirectoryExists($patchDir);

        $patchFile = $patchDir.'/report-'.$report->id.'-'.date('Ymd_His').'.patch';
        File::put($patchFile, $patch);

        $checkResult = $this->runGitApplyCheck($patchFile);
        if (! $checkResult['ok']) {
            return [
                'ok' => false,
                'message' => 'Patch non valida: '.$checkResult['error'],
                'patch_file' => $patchFile,
                'patch_source' => $patchSource,
                'branch' => $branchName,
            ];
        }

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
        $payload = [
            'model' => $model,
            'temperature' => 0.1,
            'max_tokens' => 500,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Return only one fenced ```patch``` block with unified diff. Do not add explanations.',
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
                    'error_class' => $e::class,
                    'error' => $e->getMessage(),
                ]);
            }

            return null;
        }

        if (! $response->successful()) {
            if ($this->shouldLogDebug()) {
                Log::warning('ai_orchestrator.apply.patch_fallback.failed', [
                    'report_id' => $report->id,
                    'status' => $response->status(),
                    'body_preview' => mb_substr($response->body(), 0, 1000),
                ]);
            }

            return null;
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');

        if ($this->shouldLogDebug()) {
            Log::info('ai_orchestrator.apply.patch_fallback.success', [
                'report_id' => $report->id,
                'response_chars' => mb_strlen($content),
            ]);
        }

        return $this->extractPatch($content);
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
            "- Keep patch minimal and safe.\n\n".
            "Error message: {$report->message}\n".
            "Exception class: {$report->exception_class}\n".
            "File: {$report->file}:{$report->line}\n".
            "Offending line: {$offendingLine}\n".
            "Trace:\n{$trace}\n\n".
            ($codeContext !== '' ? "Code context:\n{$codeContext}\n\n" : '').
            "Current AI analysis:\n".(string) ($report->ai_solution ?? '');
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

    private function shouldLogDebug(): bool
    {
        return (bool) config('app.debug', false);
    }
}
