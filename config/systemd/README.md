# systemd configuration

This folder includes systemd unit examples to keep the queue worker and optional llama.cpp server running in production.

## How to use it

1) Copy the unit file:

```bash
sudo cp packages/warp/laravel-ai-code-orchestrator/config/systemd/laravel-queue-work.service /etc/systemd/system/laravel-queue-work.service
```

2) Reload systemd and enable the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-queue-work.service
```

3) Start the service:

```bash
sudo systemctl start laravel-queue-work.service
```

4) Check status/logs:

```bash
sudo systemctl status laravel-queue-work.service
```

## Llama server (optional)

The llama.cpp unit is a template so the user is dynamic. Replace `your-username` with your username.

1) Copy the unit file:

```bash
sudo cp packages/warp/laravel-ai-code-orchestrator/config/systemd/llama-server@.service /etc/systemd/system/llama-server@.service
```

2) Reload systemd and enable the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable llama-server@your-username
```

3) Start the service:

```bash
sudo systemctl start llama-server@your-username
```

4) Check status/logs:

```bash
sudo systemctl status llama-server@your-username
```

## Notes

- If you use a different queue, update the `--queue=default` option.
