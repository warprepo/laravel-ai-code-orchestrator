# Laravel AI Code Orchestrator

Questo package intercetta gli errori di Laravel 11 o superiore, raccoglie il contesto di codice, invia l'errore a un provider AI (OpenAI/Groq/Gemini) e invia una email all'amministratore con la soluzione suggerita. Gli errori vengono anche salvati in DB.

## A cosa serve

- Diagnosi rapida degli errori con contesto reale del codice.
- Notifica via email con suggerimenti dell'AI.
- Storico degli errori in tabella `ai_error_reports`.

## Configurazione rapida

Variabili principali nel `.env`:

```env
AI_CODE_ORCHESTRATOR_ENABLED=true
AI_CODE_ORCHESTRATOR_ADMIN_EMAIL=admin@example.com
AI_CODE_ORCHESTRATOR_PROVIDER=gemini
AI_CODE_ORCHESTRATOR_AI_LANGUAGE=it
AI_CODE_ORCHESTRATOR_QUEUE=default
AI_CODE_ORCHESTRATOR_STORE_ERRORS=true
```

Provider (consigliato Gemini):

```env
GEMINI_API_KEY=your-gemini-key
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_MODEL=gemini-flash-lite-latest
```

## Test

Usa il comando artisan incluso nel package:

Prima di testare in locale, avvia un worker queue in un altro terminale:

```bash
php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120
```

Poi esegui:

```bash
php artisan ai-orchestrator:report "Test AI" --provider=gemini --language=it
```

Controlla la tabella `ai_error_reports` (campi `status` e `ai_solution`).

## Produzione

Avvia il worker queue con un process manager. Sono supportate due opzioni: Supervisor o systemd.

### Supervisor

File di esempio:
`packages/warp/laravel-ai-code-orchestrator/config/supervisor/laravel-ai-orchestrator.conf`

Istruzioni:
`packages/warp/laravel-ai-code-orchestrator/config/supervisor/README.md`

### systemd

Unit file di esempio:
`packages/warp/laravel-ai-code-orchestrator/config/systemd/laravel-ai-orchestrator.service`

Istruzioni:
`packages/warp/laravel-ai-code-orchestrator/config/systemd/README.md`
