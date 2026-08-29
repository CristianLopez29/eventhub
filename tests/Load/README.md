# Load test

A k6 scenario against `GET /events`, the endpoint the Redis decorator exists for.

## Running it

```bash
make load-test
```

That wraps:

```bash
docker run --rm -i --network eventhub_default -e BASE_URL=http://nginx \
  grafana/k6 run - < tests/Load/stress.js
```

Run it against a **local** stack only, and seed some data first
(`docker compose exec php bin/console app:sync-events`) or the run measures an empty
result set.

## Options

| Variable | Default | Meaning |
|----------|---------|---------|
| `BASE_URL` | `http://nginx` | API base URL, reachable from the k6 container |
| `API_USERNAME` / `API_PASSWORD` | `admin` / `secret123` | The user the Makefile creates |
| `STARTS_AT` / `ENDS_AT` | 2024 full year | Search window |

## Why setup() throws instead of continuing

The previous version logged in with `adminpass`, a password no user has. `loginRes.json('token')`
returned `null`, every request went out unauthenticated, and k6 happily reported latency for
**2650 consecutive 401s** — with `http_req_failed` at 100% and the run still called a load
test. `setup()` now fails loudly on a non-200 login or a missing token, so a credentials
mistake can never be mistaken for a performance result again.

## Thresholds

```
http_req_failed   rate < 1%      requests answer
server_errors     count == 0     none of them 5xx
search_duration   p(95) < 1000ms reported, deliberately loose
```

`server_errors` is the headline claim. The latency threshold is deliberately loose and is
**not** a performance target: the compose stack runs `APP_ENV=dev` — profiler on, debug
logging on — off a bind-mounted volume, which on Windows makes every PHP file read cross a
filesystem boundary. A measured p(95) of ~99 ms there says more about the environment than
about the query, which answers in a fraction of that when served sequentially.

Reproduce a meaningful number on the production image instead
(`compose.prod.yaml`, `APP_ENV=prod`, opcache warm), not here.

## The suite leaves no data behind

This scenario is read-only, so it adds nothing to the database. The PHPUnit suite writes
fixtures but removes exactly what it created — see
`tests/EventIntegration/Support/CleansUpItsOwnData.php`.
