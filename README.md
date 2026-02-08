# Laravel AI Code Orchestrator

This package intercepts Laravel 11 (or higher) errors, collects code context, sends the error to an AI provider (OpenAI/Groq/Gemini/Llama), and emails the administrator with the suggested fix. Errors are also stored in the database.

## What it is for

- Faster error diagnosis with real code context.
- Email notifications with AI suggestions.
- Error history stored in `ai_error_reports`.

## Provider selection

Suggested usage:

- OpenAI: best quality but tighter limits/costs unless you have a paid plan.
- Groq/Gemini: good quality with broader limits for remote APIs.
- Llama (local): no API limits and better privacy, but requires local CPU/RAM.

## Quick setup

Main `.env` variables:

```env
AI_CODE_ORCHESTRATOR_ENABLED=true
AI_CODE_ORCHESTRATOR_ADMIN_EMAIL=admin@example.com
AI_CODE_ORCHESTRATOR_PROVIDER=llama
AI_CODE_ORCHESTRATOR_AI_LANGUAGE=en
AI_CODE_ORCHESTRATOR_QUEUE=default
AI_CODE_ORCHESTRATOR_STORE_ERRORS=true
```

Remote providers (examples):

OpenAI:

```env
OPENAI_API_KEY=your-openai-key
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MODEL=gpt-4o-mini
OPENAI_MAX_TOKENS=2000
```

Groq:

```env
GROQ_API_KEY=your-groq-key
GROQ_BASE_URL=https://api.groq.com/openai/v1
GROQ_MODEL=llama-3.1-8b-instant
GROQ_MAX_TOKENS=8192
```

Gemini:

```env
GEMINI_API_KEY=your-gemini-key
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_MODEL=gemini-flash-lite-latest
GEMINI_MAX_TOKENS=8192
```

Llama local provider (llama.cpp / OpenAI-compatible):

```env
LLAMA_BASE_URL=http://127.0.0.1:8080/v1
LLAMA_MODEL=local-model
LLAMA_API_KEY=
```

Llama context memory (optional):

```env
LLAMA_INDEX_ENABLED=true
LLAMA_INDEX_CACHE_SECONDS=3600
LLAMA_INDEX_MAX_FILES=2000
LLAMA_INDEX_MAX_CHARS=6000
LLAMA_PREVIOUS_ERRORS_ENABLED=true
LLAMA_PREVIOUS_ERRORS_LIMIT=5
LLAMA_PREVIOUS_ERRORS_MAX_CHARS=4000
```

## Llama.cpp installation (Ubuntu, CLI)

Install dependencies:

```bash
sudo apt update
sudo apt install -y build-essential cmake git
```

Clone and build:

```bash
git clone https://github.com/ggerganov/llama.cpp
cd llama.cpp
cmake -B build
cmake --build build --config Release -j
```

Download a GGUF model (example):

```bash
mkdir -p models
wget -O models/qwen2.5-coder-7b-q4_k_m.gguf \
https://huggingface.co/Qwen/Qwen2.5-Coder-7B-Instruct-GGUF/resolve/main/qwen2.5-coder-7b-instruct-q4_k_m.gguf
```

Run the OpenAI-compatible server (local only):

```bash
./build/bin/llama-server \
  -m models/qwen2.5-coder-7b-q4_k_m.gguf \
  --host 127.0.0.1 \
  --port 8080 \
  --ctx-size 4096 \
  --threads 8 \
  --alias local-model
```

Configure the package:

```env
AI_CODE_ORCHESTRATOR_PROVIDER=llama
LLAMA_BASE_URL=http://127.0.0.1:8080/v1
LLAMA_MODEL=local-model
LLAMA_MAX_TOKENS=1500
```

## Testing

Use the included artisan command:

Before testing locally, start a queue worker in another terminal:

```bash
php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120
```

Then run:

```bash
php artisan ai-orchestrator:report "Test AI" --provider=gemini --language=en
```

Check the `ai_error_reports` table (fields `status` and `ai_solution`).

## Production

Run the queue worker with a process manager. Two options are supported: Supervisor or systemd.

### Supervisor

Example config:
`packages/warp/laravel-ai-code-orchestrator/config/supervisor/*.conf`

Instructions:
`packages/warp/laravel-ai-code-orchestrator/config/supervisor/README.md`

### systemd

Example unit file:
`packages/warp/laravel-ai-code-orchestrator/config/systemd/*.service`

Instructions:
`packages/warp/laravel-ai-code-orchestrator/config/systemd/README.md`

## Email example

![Email example](images/email.png)
