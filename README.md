# EventHub — Event Integration Microservice

[![CI](https://github.com/CristianLopez29/eventhub/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/CristianLopez29/eventhub/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/CristianLopez29/eventhub/branch/main/graph/badge.svg)](https://codecov.io/gh/CristianLopez29/eventhub)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](phpstan.neon)
[![Symfony](https://img.shields.io/badge/Symfony-8-black.svg)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

PHP 8.4 microservice that ingests event plans from an external XML provider and serves a
date-range search endpoint. Symfony 8, Hexagonal Architecture, CQRS with segregated ports.

The **CI** badge reflects the last run of [`.github/workflows/ci.yml`](.github/workflows/ci.yml)
on `main`, which blocks on three gates: `composer audit`, the PHPUnit suite against real
MySQL and Redis, and PHPStan level 9. A hardcoded "N tests passing" badge would only be
accurate on the day someone edited it, so the count is not written out here.

The **codecov** badge is line coverage from that same suite, and it is *not* a fourth gate:
every status in [`codecov.yml`](codecov.yml) is `informational`, so a drop reports on the
pull request but never blocks a merge. Coverage is read as a diff — which lines this change
left untested — rather than as a number to defend.

---

## Quick Start

Requires Docker and Make.

```bash
make run
```

Builds the images, starts the containers (php-fpm, nginx, MySQL, Redis, mock provider),
**generates a JWT key pair and an `APP_SECRET` unique to this checkout**, installs
dependencies, migrates both databases, creates the default user and runs a first sync.
The API is then at **http://localhost:8000**.

No secret is committed to this repository. `make jwt-keys` and `make app-secret` write
generated values into `.env.local` and `.env.test.local`, both gitignored.

### Make targets

| Target | Description |
|--------|-------------|
| `make run` | Full first-time setup and start |
| `make init` | Build, start, generate secrets — no deps, no DB |
| `make setup` | Deps + databases + migrations + default user + first sync |
| `make start` / `make stop` / `make build` | Container lifecycle |
| `make bash` | Shell into the PHP container |
| `make test` | PHPUnit — Unit, Integration, Acceptance |
| `make stan` | PHPStan level 9 over `src` |
| `make audit` | `composer audit` against known vulnerability advisories |
| `make coverage` | The suite with line coverage (needs pcov in the container) |
| `make load-test` | k6 against the search endpoint — see [tests/Load/README.md](tests/Load/README.md) |
| `make jwt-keys` / `make app-secret` | Generate secrets, idempotent |
| `make cache-clear` | `cache:clear` + `redis-cli flushall` |

**Default credentials** created by `make run` / `make setup`: `admin` / `secret123`.
Create more with `bin/console app:create-user <username> <password>`.

**Interactive API docs:** Swagger UI at http://localhost:8000/api/doc (public), raw
OpenAPI at `/api/doc.json`.

---

## API

Every response — success, domain failure, authentication failure, routing failure — uses
the same envelope:

```json
{ "data": ..., "error": null }
{ "data": null, "error": { "code": "MACHINE_CODE", "message": "..." } }
```

### `POST /login`

```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"secret123"}'
```

```json
{ "data": { "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." }, "error": null }
```

Rate limited: five failed attempts per minute per username, five times that per source IP.
A successful login resets the counter; exceeding it returns `429 TOO_MANY_REQUESTS`.

### `GET /events`

Returns events whose time range overlaps the requested interval, ordered by start date
ascending, paginated.

**Headers:** `Authorization: Bearer <jwt_token>`

| Query parameter | Required | Default | Notes |
|---|---|---|---|
| `starts_at` | yes | — | `YYYY-MM-DDTHH:mm:ss` |
| `ends_at` | yes | — | `YYYY-MM-DDTHH:mm:ss`, must not precede `starts_at` |
| `page` | no | `1` | 1-based |
| `per_page` | no | `50` | maximum `100` |

```bash
curl "http://localhost:8000/events?starts_at=2024-01-01T00:00:00&ends_at=2024-12-31T23:59:59&page=1&per_page=50" \
  -H "Authorization: Bearer <token>"
```

```json
{
  "data": {
    "events": [
      {
        "id": "f8f51b1c-6c9d-572e-b7ed-71590274e676",
        "title": "Rock Festival 2024",
        "start_date": "2024-08-15",
        "start_time": "19:00:00",
        "end_date": "2024-08-15",
        "end_time": "23:00:00",
        "min_price": 45,
        "max_price": 150
      }
    ],
    "meta": { "page": 1, "per_page": 50, "total": 8, "total_pages": 1 }
  },
  "error": null
}
```

`id` is a UUID v5 derived deterministically from the provider's `plan_id`, so a resend
updates the same row rather than duplicating it.

Only events with `sell_mode: "online"` are stored and returned. Past events stay queryable
even after the provider stops listing them.

### `GET /health` · `GET /health/ready`

| Route | Checks | Auth |
|---|---|---|
| `/health` | nothing — liveness only | public |
| `/health/ready` | MySQL and Redis, `503` if either is down | `X-Health-Check-Token` when `HEALTHCHECK_TOKEN` is set |

Liveness deliberately touches no dependency: an orchestrator *restarts* whatever fails
liveness, and a restart cannot fix a database that is down. That is readiness' job.

### Error codes

| Code | Status | When |
|---|---|---|
| `INVALID_PARAMETERS` | 400 | `starts_at` or `ends_at` missing |
| `INVALID_DATE_FORMAT` | 400 | Not `YYYY-MM-DDTHH:mm:ss`, or a rolled-over date like `2024-13-45` |
| `INVALID_DATE_RANGE` | 400 | `ends_at` earlier than `starts_at` |
| `INVALID_PAGINATION` | 400 | `page` < 1, `per_page` outside 1..100, or non-numeric |
| `AUTHENTICATION_REQUIRED` | 401 | No bearer token |
| `INVALID_TOKEN` / `TOKEN_EXPIRED` | 401 | Token rejected |
| `INVALID_CREDENTIALS` | 401 | Wrong username or password |
| `TOO_MANY_REQUESTS` | 429 | Login throttled |
| `FORBIDDEN` | 403 | Wrong or missing health-check token |
| `NOT_FOUND` / `METHOD_NOT_ALLOWED` | 404 / 405 | Routing |
| `INTERNAL_SERVER_ERROR` | 5xx | Message replaced — internals never reach the client |

---

## Architecture

### Hexagonal / Ports & Adapters

```
src/EventIntegration/
├── Domain/                 # Pure PHP, zero framework
│   ├── Entities/           # Event, Zone
│   ├── ValueObjects/       # EventId, Price, ZoneName
│   ├── Enums/              # SellMode
│   ├── Repositories/       # Ports (interfaces)
│   └── Exceptions/         # DomainException + the four that implement it
│
├── Application/            # Depends on Domain only
│   ├── UseCases/           # SearchEvents, SyncProviderEvents
│   ├── DTOs/               # SearchEventsInput/Result, SyncEventsInput, SyncResult
│   ├── Transformers/       # Domain → array
│   └── Contracts/          # EventCacheInvalidator
│
└── Infrastructure/         # Adapters
    ├── Controllers/        # SearchEvents, Login, Health, Readiness
    ├── Repositories/       # DoctrineEventRepository, ProviderClient
    ├── Cache/              # RedisCachedEventRepository (decorator)
    ├── Persistence/        # EventModel, ZoneModel (Doctrine)
    ├── Console/            # app:sync-events, app:create-user
    ├── Security/           # User, UserProvider, UserRepository
    ├── Health/             # DependencyProbe
    ├── Http/               # ApiResponse, ErrorCode
    └── Listeners/          # ExceptionListener, JwtAuthenticationListener
```

**Dependency rule:** `Domain` → nothing. `Application` → `Domain`. `Infrastructure` →
both. A domain exception carries its machine code but never an HTTP status: mapping a
failure onto a status is an Infrastructure decision.

### CQRS — segregated ports

There is no god-interface. One port per side:

| Port | Side | Consumer |
|---|---|---|
| `SearchEventsRepository` | read | `SearchEvents` |
| `SaveEventRepository` | write | `SyncProviderEvents` |
| `ProviderClientInterface` | read (external) | `SyncEventsCommand` |
| `EventCacheInvalidator` | write side-effect | `SyncProviderEvents` |

A new repository method goes into the port matching its side, or into a new port — never
widen a read port with a write method.

### Redis decorator

`RedisCachedEventRepository` decorates `DoctrineEventRepository` through Symfony's
`decorates:` key and types its inner dependency as the intersection
`SearchEventsRepository&SaveEventRepository` — satisfying both ports without
reintroducing one fat interface.

Namespace `event_integration`:

| Key | TTL | Notes |
|---|---|---|
| `event_<uuid>` | 3600 s | Single event |
| `events_search_<Ymd>_<Ymd>_<limit>_<offset>` | 300 s | One page of results |
| `events_count_<Ymd>_<Ymd>` | 300 s | Total for the range |

Day-level granularity is deliberate: an `His` suffix would mint a new key every second and
never hit. `save()` deletes only that event's key; the whole namespace is cleared **once
per sync**, from the use case, not once per saved event.

### Data flows

**Read** — `SearchEventsController` → `SearchEvents` → `SearchEventsRepository` (resolved
to the decorator) → cache hit, or `DoctrineEventRepository` → `EventTransformer` →
`ApiResponse`.

The paged query resolves a page of ids first and fetch-joins zones onto that set
afterwards. `LIMIT` applied directly to a fetch-joined collection counts *zone* rows, not
events, and would silently truncate an event's zones.

**Write** — `app:sync-events` → `ProviderClient` (fetches XML, maps to domain `Event[]`)
→ `SyncProviderEvents` → `SaveEventRepository::save()` per event → a single
`invalidateSearchCache()` after the loop.

The XML→domain translation lives entirely in the adapter; the use case receives typed
`Event` objects and returns a `SyncResult` of inserted / updated / skipped.
`ProviderClient` wraps the HTTP client in `RetryableHttpClient` (3 attempts) and **never
throws** — on failure it logs and returns `[]`, so a bad provider response cannot break
the sync.

In production a `scheduler` container runs the sync every `SYNC_INTERVAL_SECONDS`.

### Mock provider

A dedicated container simulates the external XML API:

- **static** (default) — serves the fixtures in [resources/](resources/).
- **dynamic** — emits random XML with real data-quality problems (invalid dates,
  malformed prices, offline events, missing fields): `PROVIDER_MODE=dynamic make start`.

---

## Testing

```bash
make test                                             # everything
docker compose exec php bin/phpunit --testsuite Unit  # one suite
docker compose exec php bin/phpunit --filter test_should_paginate_events_and_report_totals
```

```
tests/EventIntegration/
├── Unit/           # Domain, VOs, use cases, ProviderClient parsing — no DB, no kernel
├── Integration/    # Doctrine and Redis repositories, sync command
├── Acceptance/     # Full HTTP stack: JWT, envelope, pagination, health, throttling
├── Builders/       # EventBuilder, SearchEventsInputBuilder
└── Support/        # CleansUpItsOwnData
tests/Load/stress.js
```

Integration and Acceptance hit **real MySQL and Redis** — there is no SQLite fallback, the
containers must be up. Test method names are `test_should_<expected>_when_<condition>`;
fixtures are built with the Builders, not inline `new Event(...)` chains.

### The suite leaves no trace

It used to `TRUNCATE` events and zones, which is only safe while the connection happens to
point at a throwaway schema — a mistyped `DATABASE_URL` and the run destroys real rows.

`CleansUpItsOwnData` snapshots the ids that already exist and deletes **only what the test
added**. Verified in both directions: after a full run the test schema is back to
`events=0 zones=0 users=0`, and with rows inserted beforehand the suite passes and leaves
them untouched.

### Load test

`make load-test` drives `GET /events` with k6 (`grafana/k6:latest`, v2.0.0-rc1 at the time
of the run): 5 virtual users for 30 s after a cache-warming `setup()` that **throws** on a
failed login rather than reporting latency for a stream of 401s. Thresholds gate on "every
request answers and none 5xx"; latency is recorded, not asserted.

**Measured locally on 2026-08-29** — Intel Core i5-11400H (6c/12t), 32 GB RAM, Windows 10 +
Docker Desktop (WSL2), the **development** stack (`APP_ENV=dev`, profiler on, code on a
bind mount). **Not** the production VPS, whose image runs `APP_ENV=prod` with opcache warm
and would answer faster.

```
http_reqs .............: 2031  (64.7/s over 30s)
http_req_failed .......: 0.00%   ✓ 0     ✗ 2031      # every request answered
server_errors .........: 0                           # zero 5xx  ← the headline
checks ................: 100.00% ✓ 6075  ✗ 0
http_req_duration .....: avg=63ms  med=60ms  p(90)=77.6ms  p(95)=84.4ms  max=915ms
```

Full console output and an HTML report are committed under
[tests/Load/results/](tests/Load/results/); regenerate them with `make load-test`. See
[tests/Load/README.md](tests/Load/README.md) for the scenario rationale.

---

## Quality gates

Both run locally and in CI, and both must pass before every commit:

```
make audit   # composer audit — zero advisories
make test    # all suites green
make stan    # PHPStan level 9 over src, zero errors
```

PHPStan reads the **dev** container dump; after touching `services.yaml` run
`bin/console cache:warmup` or the analysis lies.

### Deliberate technical debt

Conscious simplifications carry a `// debt:` marker naming the ceiling and what should
trigger the upgrade. Currently two: the full-namespace cache clear on sync, and the
drop-and-reinsert of an event's zones on update.

---

## Deployment

The production stack is a multi-stage [`Dockerfile`](Dockerfile) (nginx + php-fpm,
dependencies installed `--no-dev` against the same PHP that runs them) and
[`compose.prod.yaml`](compose.prod.yaml) with its own MySQL, Redis and scheduler.

**No service publishes a host port.** A shared [Traefik](deploy/traefik/) instance
terminates TLS for every app on the box and reaches this one over an `edge` Docker
network; the database and cache stay on a private `internal` network. That is not
tidiness: Docker writes published ports straight into iptables ahead of `ufw`, so a
published port is reachable from the internet even when the firewall reports it denied.

Full instructions — host setup, the shared proxy, verification, redeploys, backups and the
gotchas of running several apps on one box — are in **[DEPLOY.md](DEPLOY.md)**. Start from
[`.env.production.example`](.env.production.example), never from the development `.env`.

### Environment variables beyond the Symfony defaults

| Variable | Default | Purpose |
|---|---|---|
| `HEALTHCHECK_TOKEN` | *(empty)* | Gates `/health/ready`. Empty leaves it open, which is what local development wants |
| `JWT_PASSPHRASE` | — | Protects the key pair. Generated per install; regenerate keys and passphrase together |
| `JWT_TOKEN_TTL` | `3600` | Token lifetime in seconds |
| `TRUSTED_PROXIES` | `172.16.0.0/12` | The Docker bridge range Traefik reaches the app from |
| `PROVIDER_EVENTS_URL` | mock provider | The XML feed to ingest |
| `SYNC_INTERVAL_SECONDS` | `3600` | How often the scheduler container syncs |
| `APP_DOMAIN` | — | Hostname Traefik routes to this app (production only) |
| `DB_ROOT_PASSWORD` | — | Initialises the MySQL container; the app never uses it |

---

## Design decisions

**Why hexagonal?** The core logic — ingestion, date-range search, price aggregation — is
isolated from framework and I/O, so business rules are testable in pure PHP and the cache
strategy is one adapter swap away.

**Why CQRS with segregated ports?** Read and write have different performance and
consistency needs. Segregating them at the port level means the read path can be cached
and paginated without the write path noticing, and interface segregation stops a fat
repository from accumulating.

**Why day-level cache keys?** Searches span full days. `Ymd` makes a repeated search hit;
`Ymd_His` would mint a key per second and never hit.

**Why a mock provider?** It makes local development independent of the network and lets
the suite exercise malformed data, failures and schema drift on demand.

---

## Technology

| Layer | Technology |
|---|---|
| Language | PHP 8.4 (`declare(strict_types=1)` everywhere) |
| Framework | Symfony 8 |
| Database | MySQL 8.0, Doctrine ORM 3 |
| Cache | Redis |
| Web | nginx + php-fpm |
| Auth | JWT (LexikJWTAuthenticationBundle) |
| Docs | NelmioApiDocBundle (OpenAPI from PHP attributes) |
| Testing | PHPUnit 11, k6 |
| Static analysis | PHPStan level 9 |
| Containers | Docker Compose, Traefik in production |

---

## License

Copyright © 2026 Cristian López.

This project is licensed under the **MIT License** — see [LICENSE](./LICENSE) for the full
text. In short: use it, modify it and ship it in anything you like, commercial or not, as
long as the copyright notice travels with it. No warranty.
