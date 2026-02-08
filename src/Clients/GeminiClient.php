<?php

namespace Warp\LaravelAiCodeOrchestrator\Clients;

use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiClient implements AiClientInterface
{
    public function analyze(Throwable $throwable, array $context): string
    {
        $config = config('ai-code-orchestrator.ai.gemini');
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        $model = $config['model'] ?? 'gemini-1.5-flash';
        $apiKey = $config['api_key'] ?? '';

        $language = config('ai-code-orchestrator.ai.language', 'it');
        $systemPrompt = $this->resolveSystemPrompt($language);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt."\n\n".$this->buildPrompt($throwable, $context)],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $config['temperature'] ?? 0.2,
                'maxOutputTokens' => $config['max_tokens'] ?? $config['max_output_tokens'] ?? 400,
            ],
        ];

        $response = Http::timeout(config('ai-code-orchestrator.ai.timeout', 15))
            ->post($baseUrl.'/models/'.$model.':generateContent?key='.$apiKey, $payload);

        if (! $response->successful()) {
            $body = $response->body();
            return 'AI error '.$response->status().': '.($body !== '' ? $body : 'empty response body');
        }

        $json = $response->json();
        $content = data_get($json, 'candidates.0.content.parts.0.text');

        if (is_string($content) && $content !== '') {
            return $content;
        }

        $raw = json_encode($json);
        return 'AI empty response. Raw: '.($raw !== false ? $raw : 'unserializable');
    }

    private function buildPrompt(Throwable $throwable, array $context): string
    {
        $trace = $context['filtered_trace'] ?? $throwable->getTraceAsString();
        $codeContext = $context['code_context'] ?? null;

        return "Errore: {$throwable->getMessage()}\n".
            "Classe: ".get_class($throwable)."\n".
            "File: {$throwable->getFile()}:{$throwable->getLine()}\n".
            "URL: ".($context['url'] ?? 'n/a')."\n".
            "Metodo: ".($context['method'] ?? 'n/a')."\n".
            "Utente ID: ".($context['user_id'] ?? 'n/a')."\n".
            "Trace:\n{$trace}\n".
            ($codeContext ? "\nContesto codice:\n{$codeContext}\n" : '');
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
}
