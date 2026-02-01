# Laravel AI Code Orchestrator

This package intercepts Laravel 11 (or higher) errors, collects code context, sends the error to an AI provider (OpenAI/Groq/Gemini), and emails the administrator with the suggested fix. Errors are also stored in the database.

## What it is for

- Faster error diagnosis with real code context.
- Email notifications with AI suggestions.
- Error history stored in `ai_error_reports`.

## Quick setup

Main `.env` variables:

```env
AI_CODE_ORCHESTRATOR_ENABLED=true
AI_CODE_ORCHESTRATOR_ADMIN_EMAIL=admin@example.com
AI_CODE_ORCHESTRATOR_PROVIDER=gemini
AI_CODE_ORCHESTRATOR_AI_LANGUAGE=en
AI_CODE_ORCHESTRATOR_QUEUE=default
AI_CODE_ORCHESTRATOR_STORE_ERRORS=true
```

Provider (Gemini example):

```env
GEMINI_API_KEY=your-gemini-key
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_MODEL=gemini-flash-lite-latest
```

## Testing

Use the included artisan command:

```bash
php artisan ai-orchestrator:report "Test AI" --provider=gemini --language=en
```

Check the `ai_error_reports` table (fields `status` and `ai_solution`).

## Production

Run the queue worker with a process manager. Two options are supported: Supervisor or systemd.

### Supervisor

Example config:
`packages/warp/laravel-ai-code-orchestrator/supervisor/laravel-ai-orchestrator.conf`

Instructions:
`packages/warp/laravel-ai-code-orchestrator/supervisor/README.md`

### systemd

Example unit file:
`packages/warp/laravel-ai-code-orchestrator/systemd/laravel-ai-orchestrator.service`

Instructions:
`packages/warp/laravel-ai-code-orchestrator/systemd/README.md`
