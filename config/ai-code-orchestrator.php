<?php

return [
    'enabled' => env('AI_CODE_ORCHESTRATOR_ENABLED', true),

    'admin_email' => env('AI_CODE_ORCHESTRATOR_ADMIN_EMAIL', 'admin@example.com'),
    'mail_subject' => env('AI_CODE_ORCHESTRATOR_MAIL_SUBJECT', 'Soluzione Errore (AI)'),
    'queue' => env('AI_CODE_ORCHESTRATOR_QUEUE', 'default'),

    'store_errors' => env('AI_CODE_ORCHESTRATOR_STORE_ERRORS', true),
    'allow_manual_reports' => env('AI_CODE_ORCHESTRATOR_ALLOW_MANUAL_REPORTS', false),
    'manual_report_token' => env('AI_CODE_ORCHESTRATOR_MANUAL_REPORT_TOKEN'),
    'ignore_exceptions' => [
        // Illuminate\Validation\ValidationException::class,
        // Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    'ai' => [
        'provider' => env('AI_CODE_ORCHESTRATOR_PROVIDER', 'openai'),
        'language' => env('AI_CODE_ORCHESTRATOR_AI_LANGUAGE', 'it'),
        'timeout' => env('AI_CODE_ORCHESTRATOR_TIMEOUT', 120),
        'system_prompt' => env('AI_CODE_ORCHESTRATOR_SYSTEM_PROMPT'),
        'context' => [
            'depth' => env('AI_CODE_ORCHESTRATOR_CONTEXT_DEPTH', 1),
            'snippet_lines' => env('AI_CODE_ORCHESTRATOR_SNIPPET_LINES', 30),
            'max_chars' => env('AI_CODE_ORCHESTRATOR_CONTEXT_MAX_CHARS', 6000),
            'max_frames' => env('AI_CODE_ORCHESTRATOR_CONTEXT_MAX_FRAMES', 4),
            'max_block_lines' => env('AI_CODE_ORCHESTRATOR_CONTEXT_MAX_BLOCK_LINES', 20),
            'strip_comments' => env('AI_CODE_ORCHESTRATOR_CONTEXT_STRIP_COMMENTS', true),
            'exclude_globs' => [
                'app/**/Tests/**',
                'packages/**/database/migrations/**',
                'packages/**/storage/**',
                'packages/**/cache/**',
                'packages/**/tests/**',
                'vendor/**',
                'node_modules/**',
                'storage/**',
                'bootstrap/cache/**',
                'tests/**',
            ],
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.2),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 2000),
        ],

        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
            'temperature' => env('GROQ_TEMPERATURE', 0.2),
            'max_tokens' => env('GROQ_MAX_TOKENS', 8192),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),
            'temperature' => env('GEMINI_TEMPERATURE', 0.2),
            'max_tokens' => env('GEMINI_MAX_TOKENS', env('GEMINI_MAX_OUTPUT_TOKENS', 8192)),
        ],

        'llama' => [
            'api_key' => env('LLAMA_API_KEY'),
            'base_url' => env('LLAMA_BASE_URL', 'http://127.0.0.1:8080/v1'),
            'model' => env('LLAMA_MODEL', 'local-model'),
            'temperature' => env('LLAMA_TEMPERATURE', 0.2),
            'max_tokens' => env('LLAMA_MAX_TOKENS', 2000),
            'file_index' => [
                'enabled' => env('LLAMA_INDEX_ENABLED', true),
                'cache_key' => env('LLAMA_INDEX_CACHE_KEY', 'ai-code-orchestrator.llama.file_index'),
                'cache_seconds' => env('LLAMA_INDEX_CACHE_SECONDS', 3600),
                'roots' => [
                    'app',
                    'config',
                    'packages',
                ],
                'extensions' => [
                    'php',
                    'blade.php',
                    'json',
                    'yml',
                    'yaml',
                ],
                'exclude_globs' => [
                    'vendor/**',
                    'node_modules/**',
                    'storage/**',
                    'bootstrap/cache/**',
                    'tests/**',
                ],
                'max_files' => env('LLAMA_INDEX_MAX_FILES', 2000),
                'max_chars' => env('LLAMA_INDEX_MAX_CHARS', 6000),
            ],
            'previous_errors' => [
                'enabled' => env('LLAMA_PREVIOUS_ERRORS_ENABLED', true),
                'limit' => env('LLAMA_PREVIOUS_ERRORS_LIMIT', 5),
                'max_chars' => env('LLAMA_PREVIOUS_ERRORS_MAX_CHARS', 4000),
            ],
        ],
    ],
];
