# Supervisor configuration

This folder includes a Supervisor configuration example to keep the queue worker running in production.

## How to use it

1) Copy the configuration file:

```bash
sudo cp packages/warp/laravel-ai-code-orchestrator/supervisor/laravel-ai-orchestrator.conf /etc/supervisor/conf.d/laravel-ai-orchestrator.conf
```

2) Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

3) Start the process:

```bash
sudo supervisorctl start laravel-ai-orchestrator:*
```

4) Check the status:

```bash
sudo supervisorctl status
```

## Notes

- If you use a different queue, update the `--queue=default` option.
