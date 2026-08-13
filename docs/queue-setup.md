# Queue Worker Setup

Want proof this actually works before trusting it? Run
`./deploy/verify-queue-priority.sh` — it stands up a throwaway database,
seeds 500 real bulk jobs, starts only the dedicated `otp` worker, fires a
real OTP request, and confirms it's delivered in well under a second while
the 500 bulk jobs sit completely untouched. It then stops the worker and
confirms Studio diagnostics detects the outage. Safe to run anywhere —
never touches your real `.env` database.

`QUEUE_CONNECTION=database` (the `.env.example` default) is correct for this app's scale — no argument for changing it. `sync` was explicitly rejected (blocks the login request on a slow SMS provider) and cron-drain was rejected (delays OTP by up to a minute, which breaks login). **A real, always-running queue worker is required in production**, and it must run as **two separate processes**, not one worker reading multiple queues.

## Why two processes, not one `--queue=otp,transactional,bulk` worker

Laravel's `queue:work --queue=a,b,c` checks queue `a` before `b` before `c` — but only *between* jobs. If a single worker is in the middle of processing a bulk campaign job (a real HTTP call to an SMS provider, up to ~10s per the driver timeout), it will finish that job before it looks for a waiting OTP job. Worst case: an OTP request queued right after a bulk job starts waits out that job's full duration. A **dedicated worker that only ever listens on `otp`** removes this window entirely — it is never doing anything else, so it's never "busy" when a login code arrives.

## Processes

| Process | Queues (priority order) | Purpose |
|---|---|---|
| `zenna-queue-otp` | `otp` | Login codes only. Nothing else is ever allowed to compete for this worker's attention. |
| `zenna-queue-general` | `transactional`, `bulk` | Order/verification/review/recovery messages (transactional) always drained before campaign/segment/coupon blasts (bulk). |

Queue names are configurable via `QUEUE_NAME_OTP` / `QUEUE_NAME_TRANSACTIONAL` / `QUEUE_NAME_BULK` in `.env` (see `config/queue_priorities.php`) — the config below assumes the defaults (`otp`, `transactional`, `bulk`).

## Install (supervisor — recommended)

```bash
sudo cp deploy/supervisor/zenna-queue-otp.conf /etc/supervisor/conf.d/
sudo cp deploy/supervisor/zenna-queue-general.conf /etc/supervisor/conf.d/
```

Edit both files first: set `directory` to the real deploy path, `user` to the process owner (commonly `www-data`), and confirm the `php` binary resolves on your `$PATH` (or hardcode its full path in `command`).

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start zenna-queue-otp:* zenna-queue-general:*
```

## Install (systemd — alternative)

```bash
sudo cp deploy/systemd/zenna-queue-otp.service /etc/systemd/system/
sudo cp deploy/systemd/zenna-queue-general.service /etc/systemd/system/
# edit User/Group/WorkingDirectory/php path in both files first
sudo systemctl daemon-reload
sudo systemctl enable --now zenna-queue-otp
sudo systemctl enable --now zenna-queue-general
```

## After every deploy

Worker processes cache the application code they started with — a deploy that changes code will not take effect in an already-running worker until it restarts. Run this after every deploy:

```bash
php artisan queue:restart
```

This signals both worker processes to finish their current job and exit; supervisor/systemd's `autorestart`/`Restart=always` brings them back up immediately with the new code. Add this to your deploy script, after `composer install`/migrations, before traffic is expected to resume.

## Verifying the workers are actually running

```bash
sudo supervisorctl status          # or: sudo systemctl status zenna-queue-otp zenna-queue-general
```

Both should show `RUNNING`. If either is not, `autorestart`/`Restart=always` should already be trying to bring it back — check the log files (`storage/logs/queue-otp.log`, `storage/logs/queue-general.log`, or `journalctl -u zenna-queue-otp` for systemd) for the crash reason.

## If messages stop going out: check Studio diagnostics first

`/studio/system-diagnostics` → **Queue Health** section is built for exactly this. In order of what to look at:

1. **OTP queue health** — if this shows `warning` or `action_needed`, customer login is degraded or broken *right now*. This is the single most urgent signal on the whole page.
2. **Worker heartbeat** — a general "is anything being processed" signal across all queues.
3. **Queue depth by tier** — if `bulk` depth is high but `otp`/`transactional` are near zero and heartbeat is healthy, that's normal (a campaign is mid-send), not a problem.
4. **Failed jobs** / **Failed Communications** — count of things that exhausted retries. Cross-reference with the Communications screen (`/studio/communications`, filter by "Failed") to see *why* — each failed message shows the provider's error reason inline.

If OTP queue health or worker heartbeat is red and the process status check above shows both workers `RUNNING`, check `storage/logs/laravel.log` and the worker log files for the actual exception — the process being alive doesn't guarantee it isn't stuck or crash-looping faster than supervisor's restart window.
