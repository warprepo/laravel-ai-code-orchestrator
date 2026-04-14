<?php

namespace Warp\LaravelAiCodeOrchestrator\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use Warp\LaravelAiCodeOrchestrator\Models\ErrorReport;
use Warp\LaravelAiCodeOrchestrator\Support\LlamaIndexCache;

class LlamaClient implements AiClientInterface
{
    private const int DEFAULT_TIMEOUT_SECONDS = 180;
    private const int DEFAULT_MAX_TOKENS = 900;
    private const int DEFAULT_INDEX_MAX_FILES = 10;
    private const int DEFAULT_INDEX_MAX_CHARS = 1000;
    private const int DEFAULT_PREVIOUS_ERRORS_MAX_CHARS = 1500;

    public function analyze(Throwable $throwable, array $context): string
    {
        $config = config('ai-code-orchestrator.ai.llama');
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        $apiKey = $config['api_key'] ?? '';
        $endpoint = $baseUrl.'/chat/completions';
        $timeoutSeconds = $this->resolveTimeout();
        $requestId = $this->buildRequestId();

        $language = config('ai-code-orchestrator.ai.language', 'it');
        $systemPrompt = $this->resolveSystemPrompt($language);
        $userPrompt = $this->buildPrompt($throwable, $context);
        $maxTokens = $this->resolveMaxTokens($config);

        $payload = [
            'model' => $config['model'] ?? 'local-model',
            'temperature' => $config['temperature'] ?? 0.2,
            'max_tokens' => $maxTokens,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ];

        if ($this->shouldLogDebug()) {
            Log::info('ai_orchestrator.llama.request.start', [
                'request_id' => $requestId,
                'endpoint' => $endpoint,
                'timeout_seconds' => $timeoutSeconds,
                'model' => $payload['model'],
                'max_tokens' => $maxTokens,
                'temperature' => $payload['temperature'],
                'system_prompt_chars' => mb_strlen($systemPrompt),
                'user_prompt_chars' => mb_strlen($userPrompt),
                'trace_chars' => mb_strlen((string) ($context['filtered_trace'] ?? '')),
                'code_context_chars' => mb_strlen((string) ($context['code_context'] ?? '')),
                'indexed_files' => (int) ($context['llama_file_index_count'] ?? 0),
            ]);
        }

        $client = $this->buildHttpClient($apiKey);
        $startedAt = microtime(true);

        try {
            $response = $client
                ->timeout($timeoutSeconds)
                ->post($endpoint, $payload);
        } catch (Throwable $e) {
            if ($this->shouldLogDebug()) {
                Log::error('ai_orchestrator.llama.request.exception', [
                    'request_id' => $requestId,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (! $response->successful()) {
            $body = $response->body();
            if ($this->shouldLogDebug()) {
                Log::warning('ai_orchestrator.llama.request.failed', [
                    'request_id' => $requestId,
                    'duration_ms' => $durationMs,
                    'status' => $response->status(),
                    'body_preview' => mb_substr($body, 0, 1200),
                ]);
            }

            return 'AI error '.$response->status().': '.($body !== '' ? $body : 'empty response body');
        }

        $json = $response->json();
        if ($this->shouldLogDebug()) {
            Log::info('ai_orchestrator.llama.request.success', [
                'request_id' => $requestId,
                'duration_ms' => $durationMs,
                'status' => $response->status(),
                'finish_reason' => data_get($json, 'choices.0.finish_reason'),
                'usage_prompt_tokens' => data_get($json, 'usage.prompt_tokens'),
                'usage_completion_tokens' => data_get($json, 'usage.completion_tokens'),
                'usage_total_tokens' => data_get($json, 'usage.total_tokens'),
            ]);
        }

        $content = data_get($json, 'choices.0.message.content');

        if (is_string($content) && $content !== '') {
            return $content;
        }

        $raw = json_encode($json);
        return 'AI empty response. Raw: '.($raw !== false ? $raw : 'unserializable');
    }

    private function buildHttpClient(string $apiKey): PendingRequest
    {
        $client = Http::asJson();

        if ($apiKey !== '') {
            $client = $client->withToken($apiKey);
        }

        return $client;
    }

    private function buildPrompt(Throwable $throwable, array $context): string
    {
        $trace = $context['filtered_trace'] ?? $throwable->getTraceAsString();
        $codeContext = $context['code_context'] ?? null;
        $extraContext = $this->buildLlamaContext();

        return "Errore: {$throwable->getMessage()}\n".
            "Classe: ".get_class($throwable)."\n".
            "File: {$throwable->getFile()}:{$throwable->getLine()}\n".
            "URL: ".($context['url'] ?? 'n/a')."\n".
            "Metodo: ".($context['method'] ?? 'n/a')."\n".
            "Utente ID: ".($context['user_id'] ?? 'n/a')."\n".
            "Trace:\n{$trace}\n".
            ($codeContext ? "\nContesto codice:\n{$codeContext}\n" : '').
            $extraContext;
    }

    private function resolveSystemPrompt(string $language): string
    {
        $custom = config('ai-code-orchestrator.ai.system_prompt');
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        $laravelVersion = $this->getLaravelVersion();

        return $language === 'en'
            ? "You are a technical assistant. Be concise. Do not repeat the error or stack trace. Provide only practical fix steps. Do not include a 'Cause' section. Respond in clean HTML only (use <strong> and <ul><li>) with no Markdown, no code fences, and no patch blocks."
            : "Sei un assistente tecnico. Sii conciso. Non ripetere errore o stack trace. Fornisci solo i passaggi di soluzione pratica. Non includere la sezione 'Causa'. Rispondi solo in HTML pulito (usa <strong> e <ul><li>), senza Markdown, senza blocchi di codice e senza patch.";
    }

    private function getLaravelVersion(): string
    {
        try {
            $version = app()->version();
            return $version !== '' ? $version : 'unknown';
        } catch (Throwable) {
            return 'unknown';
        }
    }

    private function buildLlamaContext(): string
    {
        $sections = [];

        $fileIndex = $this->getCachedFileIndex();
        if ($fileIndex !== '') {
            $sections[] = "Indice file (app/config/packages):\n".$fileIndex;
        }

        $previousErrors = $this->getPreviousErrorsContext();
        if ($previousErrors !== '') {
            $sections[] = "Errori precedenti:\n".$previousErrors;
        }

        if (count($sections) === 0) {
            return '';
        }

        return "\n".implode("\n\n", $sections)."\n";
    }

    private function getCachedFileIndex(): string
    {
        $config = config('ai-code-orchestrator.ai.llama.file_index', []);
        $configuredMaxFiles = isset($config['max_files']) ? (int) $config['max_files'] : self::DEFAULT_INDEX_MAX_FILES;
        $configuredMaxChars = isset($config['max_chars']) ? (int) $config['max_chars'] : self::DEFAULT_INDEX_MAX_CHARS;
        $config['max_files'] = max(1, min(self::DEFAULT_INDEX_MAX_FILES, $configuredMaxFiles));
        $config['max_chars'] = max(200, min(self::DEFAULT_INDEX_MAX_CHARS, $configuredMaxChars));
        $cacheKey = (string) ($config['cache_key'] ?? 'ai-code-orchestrator.llama.file_index');
        $config['cache_key'] = $cacheKey.'.max_files_'.$config['max_files'].'_max_chars_'.$config['max_chars'];

        $cache = new LlamaIndexCache();
        $data = $cache->getIndexData($config);

        return (string) ($data['index'] ?? '');
    }

    private function getPreviousErrorsContext(): string
    {
        $config = config('ai-code-orchestrator.ai.llama.previous_errors', []);
        $enabled = (bool) ($config['enabled'] ?? true);
        if (! $enabled) {
            return '';
        }

        $limit = (int) ($config['limit'] ?? 5);
        $configuredMaxChars = isset($config['max_chars']) ? (int) $config['max_chars'] : self::DEFAULT_PREVIOUS_ERRORS_MAX_CHARS;
        $maxChars = max(200, min(self::DEFAULT_PREVIOUS_ERRORS_MAX_CHARS, $configuredMaxChars));

        try {
            $reports = ErrorReport::query()
                ->whereNotNull('ai_solution')
                ->where('ai_solution', '!=', '')
                ->orderByDesc('id')
                ->limit(max(1, $limit))
                ->get(['message', 'exception_class', 'file', 'line', 'ai_solution']);

            if ($reports->isEmpty()) {
                return '';
            }

            $chunks = [];
            foreach ($reports as $report) {
                $chunks[] = "Errore: {$report->message}\n".
                    "Classe: {$report->exception_class}\n".
                    "File: {$report->file}:{$report->line}\n".
                    "Soluzione: {$report->ai_solution}";
            }

            $text = implode("\n---\n", $chunks);
            return $this->truncate($text, $maxChars);
        } catch (Throwable) {
            return '';
        }
    }

    private function truncate(string $text, int $maxChars): string
    {
        if ($maxChars <= 0 || mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars)."\n... [truncated]";
    }

    private function resolveTimeout(): int
    {
        $timeout = (int) config('ai-code-orchestrator.ai.timeout', self::DEFAULT_TIMEOUT_SECONDS);

        return max(5, $timeout);
    }

    private function resolveMaxTokens(array $config): int
    {
        $configured = isset($config['max_tokens']) ? (int) $config['max_tokens'] : self::DEFAULT_MAX_TOKENS;

        return max(64, min(self::DEFAULT_MAX_TOKENS, $configured));
    }

    private function buildRequestId(): string
    {
        try {
            return 'llama_'.bin2hex(random_bytes(8));
        } catch (Throwable) {
            return 'llama_'.str_replace('.', '', uniqid('', true));
        }
    }

    private function shouldLogDebug(): bool
    {
        return (bool) config('app.debug', false);
    }
}
