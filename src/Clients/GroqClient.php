<?php

namespace Warp\LaravelAiCodeOrchestrator\Clients;

use Illuminate\Support\Facades\Http;
use Throwable;

class GroqClient implements AiClientInterface
{
    public function analyze(Throwable $throwable, array $context): string
    {
        $config = config('ai-code-orchestrator.ai.groq');
        $baseUrl = rtrim($config['base_url'] ?? '', '/');

        $language = config('ai-code-orchestrator.ai.language', 'it');
        $systemPrompt = $this->resolveSystemPrompt($language);

        $payload = [
            'model' => $config['model'] ?? 'llama-3.1-8b-instant',
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

        $response = Http::withToken($config['api_key'] ?? '')
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
            ? "You are a technical assistant. Be concise. Do not repeat the error or stack trace. Provide only root cause and a practical fix (bullets). Project: Laravel {$laravelVersion}."
            : "Sei un assistente tecnico. Sii conciso. Non ripetere errore o stack trace. Fornisci solo causa e soluzione pratica (punti elenco). Progetto: Laravel {$laravelVersion}.";
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
