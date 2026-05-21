# EventHub — Event Integration Microservice

PHP 8.4 microservice that ingests event plans from an external XML provider and exposes a high-performance search endpoint. Built with Symfony 8, following Hexagonal Architecture and CQRS principles.

---

## Quick Start

Requires Docker and Make.

```bash
make run
```

This single command builds images, starts containers (PHP-FPM, nginx, MySQL, Redis, Mock Provider), generates JWT keys, installs dependencies, and prepares the database. The API will be available at **http://localhost:8000**.

### Other useful commands

| Command | Description |
|---------|-------------|
| `make run` | Full first-time setup and start |
| `make start` | Start existing containers |
| `make stop` | Stop containers |
| `make test` | Run PHPUnit (Unit, Integration, Acceptance) |
| `make stan` | Run PHPStan at level 9 |
| `make load-test` | Run k6 stress test against the search endpoint |
| `make bash` | Access the PHP container |
| `make cache-clear` | Clear Symfony and Redis caches |

### Get a JWT token

A default `admin` user is pre-configured. Obtain a token directly:

```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"adminpass"}'
```

### Manual sync from provider

```bash
make bash
bin/console app:sync-events
```

### Switch provider mode

The mock provider supports two modes:
- **static** (default): Serves real XML fixtures from `resources/`
- **dynamic**: Generates random XML with realistic data quality issues

```bash
PROVIDER_MODE=dynamic make start
```

---

## API Endpoints

### `POST /login`

Authenticate and receive a JWT token.

**Request:**
```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"adminpass"}'
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

### `GET /events`

Returns events whose time range overlaps with the requested interval.

**Headers:**
- `Authorization: Bearer <jwt_token>`

**Query parameters:**
- `starts_at` — ISO-like format: `YYYY-MM-DDTHH:mm:ss`
- `ends_at` — ISO-like format: `YYYY-MM-DDTHH:mm:ss`

**Example:**
```bash
curl "http://localhost:8000/events?starts_at=2024-06-01T00:00:00&ends_at=2024-06-30T23:59:59" \
  -H "Authorization: Bearer <token>"
```

**Response format:**
```json
{
  "data": {
    "events": [
      {
        "id": "291",
        "title": "Camela en concierto",
        "start_date": "2021-06-30",
        "start_time": "21:00:00",
        "end_date": "2021-06-30",
        "end_time": "22:00:00",
        "min_price": 15.00,
        "max_price": 30.00
      }
    ]
  },
  "error": null
}
```

Only events with `sell_mode: "online"` are stored and returned. Past events remain queryable even if the provider no longer includes them.

---

## Architecture

### Hexagonal / Ports & Adapters (Modular Monolith)

The codebase is organized by bounded context (`EventIntegration`) with strict dependency rules:

```
src/EventIntegration/
├── Domain/              # Pure business logic (inner hexagon)
│   ├── Entities/        # Event, Zone
│   ├── ValueObjects/    # EventId, Price, ZoneName
│   ├── Enums/           # SellMode
│   ├── Events/          # Domain events (EventSynchronized)
│   ├── Repositories/    # Ports (interfaces)
│   └── Exceptions/      # Domain-specific exceptions
│
├── Application/         # Application services (coordinating hexagon)
│   ├── UseCases/        # SearchEvents, SyncProviderEvents
│   ├── DTOs/            # Input/Output data transfer objects
│   ├── Transformers/    # API response formatting
│   └── Contracts/       # Application-level interfaces
│
└── Infrastructure/      # Adapters (framework & I/O)
    ├── Controllers/     # HTTP layer (SearchEventsController, LoginController)
    ├── Repositories/    # DoctrineEventRepository, ProviderClient
    ├── Cache/           # RedisCachedEventRepository (Decorator)
    ├── Persistence/     # Doctrine models (EventModel, ZoneModel)
    ├── Console/         # CLI commands (app:sync-events, app:create-user)
    ├── Security/        # JWT authentication (User, UserProvider)
    └── Listeners/       # ExceptionListener
```

**Dependency rule:**
- `Domain` depends on **nothing** (no framework, no DB).
- `Application` depends only on `Domain`.
- `Infrastructure` depends on `Application` and `Domain`.

### CQRS

Read and write operations are strictly separated:
- **Commands:** `SyncProviderEvents` (ingests external data into the database).
- **Queries:** `SearchEvents` (reads from database via cached repository).

### Persistence Ignorance

Domain entities (`Event`, `Zone`) do **not** extend Doctrine models. Mapping between domain entities and persistence models happens in the Infrastructure layer via repository `reconstruct` and `build` methods.

### Caching Strategy — Redis Decorator Pattern

The `EventRepositoryInterface` has two implementations:
1. `DoctrineEventRepository` — the real database access.
2. `RedisCachedEventRepository` — a transparent decorator that caches:
   - Individual events by ID (`event_<uuid>`) for 1 hour.
   - Search results by date range (`events_search_<Ymd>_<Ymd>`) for 5 minutes.

Cache invalidation happens automatically on write: saving an event clears its individual cache and all search caches.

### Mock Provider

A dedicated Docker container simulates the external XML provider:
- **Static mode:** Rotates through XML fixtures in `resources/`
- **Dynamic mode:** Generates random XML with realistic data quality issues (invalid dates, malformed prices, offline events, missing fields)

This enables testing error handling and resilience without external dependencies.

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| Language | PHP 8.4 |
| Framework | Symfony 8 |
| Database | MySQL 8.0 |
| Cache | Redis |
| Web Server | nginx + php-fpm |
| Auth | JWT (LexikJWTAuthenticationBundle) |
| Testing | PHPUnit 11, k6 |
| Static Analysis | PHPStan Level 9 |
| Containerization | Docker Compose |

---

## Testing

```bash
make test        # PHPUnit (Unit + Integration + Acceptance)
make stan        # PHPStan Level 9
make load-test   # k6 stress test
```

### Test Pyramid

- **Unit:** Domain entities, value objects, use cases, transformers.
- **Integration:** Doctrine repositories, Redis cache, console commands.
- **Acceptance:** Full HTTP stack with database and cache.
- **Load:** k6 stress test against the search endpoint.

---

## Design Decisions

### Why Hexagonal Architecture?

The core business logic (event ingestion, date-range search, price aggregation) is completely isolated from framework and infrastructure concerns. This allows:
- Swapping Symfony for another framework without touching Domain/Application.
- Testing business rules in pure PHP without a database.
- Changing the cache strategy (Redis → Memcached) by swapping one adapter.

### Why CQRS?

The read model (search events) and write model (sync from provider) have different performance characteristics and consistency requirements. Separating them allows:
- Optimizing reads with Redis caching without affecting writes.
- Scaling read and write paths independently.

### Why Day-Level Cache Granularity?

Search queries typically span full days. Using `Ymd` instead of `Ymd_His` as the cache key ensures that repeated searches for the same date range hit the cache, while `His` would generate a new key every second.

### Why a Mock Provider?

The external provider is simulated to:
- Enable local development without network dependencies.
- Test resilience against malformed data, network failures, and changing schemas.
- Demonstrate handling of real-world data quality issues.

---

## License

Proprietary — All rights reserved.
