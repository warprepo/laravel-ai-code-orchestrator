# systemd configuration

This folder includes a systemd unit example to keep the queue worker running in production.

## How to use it

1) Copy the unit file:

```bash
sudo cp packages/warp/laravel-ai-code-orchestrator/systemd/laravel-ai-orchestrator.service /etc/systemd/system/laravel-ai-orchestrator.service
```

2) Reload systemd and enable the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-ai-orchestrator.service
```

3) Start the service:

```bash
sudo systemctl start laravel-ai-orchestrator.service
```

4) Check status/logs:

```bash
sudo systemctl status laravel-ai-orchestrator.service
```

## Notes

- Verify the PHP path (`/usr/bin/php`) and the app path (`/mnt/linux-data/www/mira-park-manager`).
- Make sure the user (`www-data`) has access to the files and the `storage/` directory.
- If you use a different queue, update the `--queue=default` option.
