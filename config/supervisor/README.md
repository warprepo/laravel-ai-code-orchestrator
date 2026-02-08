# Supervisor configuration

This folder includes Supervisor configuration examples to keep the queue worker and optional llama.cpp server running in production.

## How to use it

1) Copy the configuration file:

```bash
sudo cp packages/warp/laravel-ai-code-orchestrator/config/supervisor/laravel-queue-work.conf /etc/supervisor/conf.d/laravel-queue-work.conf
```

2) Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

3) Start the process:

```bash
sudo supervisorctl start laravel-queue-work:*
```

4) Check the status:

```bash
sudo supervisorctl status
```

## Llama server (optional)

1) Copy the configuration file:

```bash
sudo cp packages/warp/laravel-ai-code-orchestrator/config/supervisor/llama-server.conf /etc/supervisor/conf.d/llama-server.conf
```

2) Update the user:

Edit `/etc/supervisor/conf.d/llama-server.conf` and change `LLAMA_USER` to the correct username.

3) Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

4) Start the process:

```bash
sudo supervisorctl start llama-server:*
```

## Notes

- If you use a different queue, update the `--queue=default` option.
