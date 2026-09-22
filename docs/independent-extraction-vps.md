# Dataminer private pilot operations

## Deployment boundary

The Laravel application and isolated browser worker are separate services. The browser service must not receive the application environment, APP_KEY, database/Redis credentials, or host/application mounts. Keep its API private, accessible only to Laravel. Set a random `BROWSER_SERVICE_SECRET` of at least 32 characters on both services. Configure `BROWSER_SERVICE_URL` in Laravel. The browser container runs unprivileged with Chromium sandbox enabled and a public-network-only proxy. Do not disable the sandbox to make deployment pass.

Keep existing approved JavaScript collectors as trusted internal legacy workloads. They are not eligible for schedules. Rebuild them as structured collectors before scheduling; the original source and datasets remain available. This release does not provide a public arbitrary-code sandbox.

## Isolated browser service

From the repository root, set a dedicated random `BROWSER_SERVICE_SECRET`, then run:

```sh
docker compose -f provision/browser/compose.yaml up -d --build
```

The supplied Compose file publishes only `127.0.0.1:3210`, drops capabilities, applies the Playwright seccomp profile, and caps CPU, memory, temporary storage and process count. Laravel on the host reaches it at `http://127.0.0.1:3210`. If Laravel is containerized, use a private network and service URL instead of publishing the browser publicly. Only the browser secret is passed into its environment. No application mounts are needed.

Check authenticated `GET /health` with a bearer service token from the application host. A successful response reports `status: ok` and the number of active contexts. Keep tokens out of shell history and monitoring logs. This container configuration has not been runtime-tested in the development workspace because Docker is unavailable; verify sandbox launch and public-network restrictions on the target VPS before promotion.

## Application

1. Back up the database, private artifact disk, and APP_KEY before deployment. Keep the key backup encrypted and separate from the VPS; saved connections cannot be recovered without it.
2. Install the locked Composer/npm dependencies, build frontend assets, run additive migrations, and restart queue workers. Run `make check` before promotion.
3. Configure MySQL and Redis. Set `REDIS_QUEUE_RETRY_AFTER=3900`, above the scrape job timeout of 3660 seconds. All hosts must use the same values. Disable public registration by leaving `PILOT_REGISTRATION_EMAILS` empty; explicitly list invited addresses to allow pilot signups. Existing users retain login access.
4. Keep AI assistance disabled. Provider credentials and paid entitlement are not required for ordinary collectors. No billing or live AI integration is supplied by this release.
5. Configure a working mail transport only if users opt in to email notifications. In-app events remain available independently.

## Cron and workers

Run Laravel scheduling every minute as the application user:

```cron
* * * * * cd /srv/dataminer && php artisan schedule:run >> /var/log/dataminer-schedule.log 2>&1
```

Supervise the queue worker with automatic restart and enough shutdown time for active collections:

```ini
[program:dataminer-worker]
command=php /srv/dataminer/artisan queue:work redis --queue=default --sleep=3 --tries=1 --timeout=3660 --memory=512
user=dataminer
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3900
redirect_stderr=true
stdout_logfile=/var/log/dataminer-worker.log
```

Do not run duplicate unmanaged workers using a shorter Redis retry interval. The scheduler coalesces missed slots into at most one catch-up occurrence and the run service prevents collector and shared-connection overlap. Retention excludes legacy history, keeps version records, and preserves the latest usable comparison baseline.

## Health and recovery

Monitor the cache key `collector_scheduler.last_tick_at` (UTC timestamp, one-hour TTL), queue backlog/failed jobs, stale running records, disk usage, browser health endpoint, and encrypted connection status. Browser service failures must be visible as failed runs, never complete empty datasets. Expired sessions pause their schedules; reconnect through the browser view, preview successfully, then resume. Device-bound login, passkeys, and sites that reject automated browsers are unsupported.

On worker termination, abandoned-run recovery releases the collector by marking stale work failed. Inspect diagnostics before retrying. Never change failed runs to complete manually. A cancellation can leave a browser request active until its bounded operation finishes; cancellation must prevent dataset publication.

## Acceptance before live use

Use controlled website and feed fixtures to verify JavaScript rendering, pagination and details, login/reconnect, unsafe addresses/XML rejection, duplicate queue delivery, cancellation, DST, missing identity, partial extraction, and private artifact ownership. Verify a full manual lifecycle with all AI provider calls forbidden. Production rollout remains contingent on the final verification evidence in `independent-extraction-progress.md`.
