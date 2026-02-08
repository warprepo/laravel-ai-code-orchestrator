<?php

namespace Warp\LaravelAiCodeOrchestrator\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;
use Warp\LaravelAiCodeOrchestrator\Models\ErrorReport;
use Warp\LaravelAiCodeOrchestrator\Support\LlamaIndexCache;

class LlamaClient implements AiClientInterface
{
    public function analyze(Throwable $throwable, array $context): string
    {
        $config = config('ai-code-orchestrator.ai.llama');
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        $apiKey = $config['api_key'] ?? '';

        $language = config('ai-code-orchestrator.ai.language', 'it');
        $systemPrompt = $this->resolveSystemPrompt($language);

        $payload = [
            'model' => $config['model'] ?? 'local-model',
            'temperature' => $config['temperature'] ?? 0.2,
            'max_tokens' => $config['max_tokens'] ?? 400,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $this->buildPrompt($throwable, $context),
                ],
            ],
        ];

        $client = $this->buildHttpClient($apiKey);

        $response = $client
            ->timeout(config('ai-code-orchestrator.ai.timeout', 15))
            ->post($baseUrl.'/chat/completions', $payload);

        if (! $response->successful()) {
            $body = $response->body();
            return 'AI error '.$response->status().': '.($body !== '' ? $body : 'empty response body');
        }

        $json = $response->json();
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
            ? "You are a technical assistant. Be concise. Do not repeat the error or stack trace. Provide only root cause and a practical fix. Respond in HTML only (no Markdown), using <strong> and <ul><li> as needed. Project: Laravel {$laravelVersion}."
            : "Sei un assistente tecnico. Sii conciso. Non ripetere errore o stack trace. Fornisci solo causa e soluzione pratica. Rispondi solo in HTML (niente Markdown), usando <strong> e <ul><li> se necessario. Progetto: Laravel {$laravelVersion}.";
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
        $maxChars = (int) ($config['max_chars'] ?? 4000);

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
}
