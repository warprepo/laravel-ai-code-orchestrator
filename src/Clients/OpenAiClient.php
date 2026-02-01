<?php

namespace Warp\LaravelAiCodeOrchestrator\Clients;

use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiClient implements AiClientInterface
{
    public function analyze(Throwable $throwable, array $context): string
    {
        $config = config('ai-code-orchestrator.ai.openai');
        $baseUrl = rtrim($config['base_url'] ?? '', '/');

        $language = config('ai-code-orchestrator.ai.language', 'it');
        $systemPrompt = $language === 'en'
            ? 'You are a technical assistant. Analyze Laravel/PHP errors and propose a short, practical fix.'
            : 'Sei un assistente tecnico. Analizza errori Laravel/PHP e proponi una soluzione breve e pratica.';

        $payload = [
            'model' => $config['model'] ?? 'gpt-4o-mini',
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
}
