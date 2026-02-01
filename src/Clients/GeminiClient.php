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
        $systemPrompt = $language === 'en'
            ? 'You are a technical assistant. Analyze Laravel/PHP errors and propose a short, practical fix.'
            : 'Sei un assistente tecnico. Analizza errori Laravel/PHP e proponi una soluzione breve e pratica.';

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
                'maxOutputTokens' => $config['max_output_tokens'] ?? 400,
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
}
