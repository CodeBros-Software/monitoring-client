# codebros-nl/monitoring-client

Ships Laravel application telemetry — requests, exceptions, slow queries,
jobs, cache hit/miss, scheduled task runs, and outgoing HTTP calls — to the
CodeBros portal's monitoring dashboard.

It reuses [Laravel Pulse](https://github.com/laravel/pulse) as
infrastructure only: Pulse's `Ingest` buffering contract and the
`pulse:work` flush loop. It does **not** use Pulse's dashboard or most of
its built-in recorders — those are optimized for lightweight sampled
aggregates, not the full per-event detail (HTTP status codes, job
success/failed/retried, scheduled task health) the portal needs. Instead,
custom recorders capture that detail and buffer it the same way Pulse
does, and a custom `Ingest` implementation batches it to the portal over
HTTP instead of writing to a local database.

## Installation

```bash
composer require codebros-nl/monitoring-client
```

Publish the config file:

```bash
php artisan vendor:publish --tag=monitoring-client-config
```

## Configuration

Set these in the client app's `.env` — the token comes from the portal's
project Monitoring tab ("Monitoring-app toevoegen"):

```env
MONITORING_ENDPOINT=https://portal.codebros.nl/api/monitoring/ingest
MONITORING_TOKEN=the-token-shown-once-when-you-create-the-app
```

Telemetry is buffered in the application's default cache store between
requests/jobs. **The cache store must be one that persists across
processes** (`file`, `database`, `redis`, `memcached`) — `array` will
silently drop everything, since it never leaves the current process. Set
`MONITORING_CACHE_STORE` to point at a specific store if the app's default
cache driver isn't suitable.

## Running the flush loop

Buffered telemetry is only sent to the portal when something calls
`Pulse::digest()`. Run Laravel Pulse's own `pulse:work` command as a
supervised background process — this package doesn't ship its own
command, it rides on Pulse's:

```bash
php artisan pulse:work
```

Under Supervisor (or an equivalent process manager), matching how you'd
run `pulse:work` for stock Pulse:

```ini
[program:monitoring-client-worker]
command=php /path/to/artisan pulse:work
autostart=true
autorestart=true
user=www-data
```

Restart it after deploys the same way you would `pulse:work`:

```bash
php artisan pulse:restart
```

## What gets sent

| Table (portal side)         | Source                                            |
|------------------------------|----------------------------------------------------|
| `monitoring_requests`        | Custom recorder — every request, with status code  |
| `monitoring_exceptions`      | Custom recorder — class, message, context, trace   |
| `monitoring_queries`         | Pulse's `SlowQueries` recorder, `threshold: 0`      |
| `monitoring_jobs`             | Custom recorder — success/failed/retried, queue     |
| `monitoring_cache`            | Pulse's `CacheInteractions` recorder                |
| `monitoring_scheduled_tasks`  | Custom recorder — Pulse ships none for this         |
| `monitoring_outgoing_requests`| Custom recorder — every HTTP client call, with status |

Not captured, by design: full request/response payloads, and any
user-level tracking (matches the portal's own privacy scope).

## Development

```bash
composer install
composer test   # vendor/bin/pest
```

Uses Orchestra Testbench to boot a minimal Laravel app for tests; no
external services required.
