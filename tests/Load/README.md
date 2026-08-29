# Load test

A k6 scenario against `GET /events`, the endpoint the Redis decorator exists for.

## Running it

```bash
make load-test
```

That wraps:

```bash
docker run --rm -i --network eventhub_default -e BASE_URL=http://nginx \
  -v "$PWD/tests/Load/results://out" -w //out \
  grafana/k6 run - < tests/Load/stress.js
```

Run it against a **local** stack only, and seed some data first
(`docker compose exec php bin/console app:sync-events`) or the run measures an empty
result set. `handleSummary` writes `results/summary.txt` and `results/report.html` at the
end (the two `import` lines at the top of `stress.js` are fetched from a CDN on start, so a
run needs outbound network — the same as pulling the `grafana/k6` image).

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
filesystem boundary. The number below says more about that environment than about the
query, which answers in a fraction of it on the production image (`APP_ENV=prod`, opcache
warm).

## Last run

`results/summary.txt` and `results/report.html`, committed beside this file, are the real
output of the run quoted in the project README:

```
2026-08-29 · i5-11400H · Docker Desktop / WSL2 · development stack
http_reqs .......: 2031   (64.7/s over 30s)
http_req_failed .: 0.00%  ✓ 0  ✗ 2031
server_errors ...: 0
checks ..........: 100.00% (6075/6075)
http_req_duration: avg=63ms  p(95)=84.4ms  max=915ms
```

## The suite leaves no data behind

This scenario is read-only, so it adds nothing to the database. The PHPUnit suite writes
fixtures but removes exactly what it created — see
`tests/EventIntegration/Support/CleansUpItsOwnData.php`.
