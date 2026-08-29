# Deploying EventHub to a VPS

Target: one Ubuntu/Debian VPS hosting **several independent applications**, each fully
containerised, behind one shared Traefik proxy that owns `:80`/`:443` and terminates TLS
for all of them.

```
                    internet
                        |
                   :80  |  :443
                        v
              +------------------+
              |     traefik      |   /srv/traefik - deployed once
              +--------+---------+
                       |  network: edge
      +----------------+----------------+---------------+
      v                v                v               v
  candidacy        ticketing        eventhub        (next app)
   app:8080         app:8080         app:8080        app:8080
      |                |                |               |
  internal         internal         internal        internal
  mysql redis      mysql redis      mysql redis     mysql redis
                                    scheduler
```

Each app keeps its own MySQL and Redis on a private `internal` network. Nothing but
Traefik binds a host port; an app's database is not reachable from another app, from the
host, or from the internet.

---

## Part 1 - Host, once

### 1.1 Packages

```bash
sudo apt update && sudo apt install -y ca-certificates curl git
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"   # log out and back in
```

No PHP, no MySQL, no nginx on the host. Everything runs in containers.

### 1.2 Firewall

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

**`ufw` does not protect published container ports.** Docker writes its rules straight
into the `DOCKER` iptables chain, which is evaluated before ufw's, so a container
publishing `3306:3306` is reachable from the internet while `ufw status` claims that port
is denied.

That is why **no service in `compose.prod.yaml` has a `ports:` key**. Verify from
**another machine** after every deploy:

```bash
nmap -Pn -p 22,80,443,3306,6379,8080 <your-server-ip>
```

Only 22, 80 and 443 may be `open`.

### 1.3 The shared network and proxy

```bash
docker network create edge

sudo mkdir -p /srv/traefik && cd /srv/traefik
# copy deploy/traefik/ from this repo here (compose.yaml + dynamic/)
echo "ACME_EMAIL=you@example.com" > .env
docker compose up -d
```

See [`deploy/traefik/README.md`](deploy/traefik/README.md). Traefik routes only containers
carrying `traefik.enable=true`, so a new container is never exposed by accident.

---

## Part 2 - This application

### 2.1 Clone and configure

```bash
sudo mkdir -p /srv/eventhub && sudo chown "$USER" /srv/eventhub
git clone <repo-url> /srv/eventhub && cd /srv/eventhub

cp .env.production.example .env
```

Fill in every `<...>` placeholder:

```bash
openssl rand -hex 32        # APP_SECRET, HEALTHCHECK_TOKEN, JWT_PASSPHRASE
openssl rand -base64 24     # DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_PASSWORD
```

`APP_DOMAIN` is what Traefik matches on. **Point its A record at the VPS before starting
the container** - Traefik's TLS-ALPN challenge fails otherwise, and repeated failures hit
Let's Encrypt rate limits.

### 2.2 Start

```bash
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml ps
docker compose -f compose.prod.yaml logs -f app
```

The `app` entrypoint rebuilds the config and route caches on every boot, generates the JWT
key pair into the `jwt_keys` volume if it is empty, waits for MySQL and runs migrations.
Those caches embed environment values, so they are built at start rather than baked into
an image that is built once and run against whatever `.env` the server provides.

**Only the `web` role migrates.** `scheduler` shares the same image but skips it
(`CONTAINER_ROLE=scheduler`), so two containers never race the same migrator.

### 2.3 First user

There is no seeder to avoid - the image ships no default account. Create one:

```bash
docker compose -f compose.prod.yaml exec app php bin/console app:create-user <username> <a-strong-password>
```

### 2.4 Provider sync

The `scheduler` container runs `app:sync-events` every `SYNC_INTERVAL_SECONDS` (default
3600). To force one now:

```bash
docker compose -f compose.prod.yaml exec app php bin/console app:sync-events
```

The mock provider container is **not** part of the production stack; point
`PROVIDER_EVENTS_URL` at the real feed.

---

## Part 3 - Verify

```bash
TOKEN=<HEALTHCHECK_TOKEN>
DOMAIN=<your-domain>

# TLS and redirect
curl -sI http://$DOMAIN | head -1                      # 301 to https
curl -s https://$DOMAIN/health                         # {"data":{"status":"ok"},"error":null}

# Readiness: 403 without the token, 200 with it
curl -so /dev/null -w '%{http_code}\n' https://$DOMAIN/health/ready
curl -s -H "X-Health-Check-Token: $TOKEN" https://$DOMAIN/health/ready

# Headers: no X-Powered-By, HSTS present
curl -sI https://$DOMAIN/health | grep -iE 'x-powered-by|strict-transport|x-frame'

# Arbitrary .php must not execute
curl -so /dev/null -w '%{http_code}\n' https://$DOMAIN/index2.php   # 404

# A real request
curl -s -X POST https://$DOMAIN/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"NAME","password":"PASSWORD"}'

curl -s "https://$DOMAIN/events?starts_at=2024-01-01T00:00:00&ends_at=2024-12-31T23:59:59" \
  -H "Authorization: Bearer TOKEN"
```

`/health/ready` answers `200` when MySQL and Redis are both reachable and `503` when
either is down, so an uptime monitor can alert on it. Point UptimeRobot at it with the
token as a custom header.

Swagger UI is at `https://$DOMAIN/api/doc` and is **public**, as it is locally. If that is
not wanted on a deployed instance, put it behind a Traefik basic-auth middleware - the app
does not gate it.

---

## Part 4 - Operating

### Redeploy

```bash
cd /srv/eventhub
git pull
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
```

`up -d` recreates only what changed. The entrypoint re-runs migrations and rebuilds
caches; the scheduler is replaced with the new image, which matters because its loop holds
code in memory.

### Logs

| Where | What |
|-------|------|
| `docker compose -f compose.prod.yaml logs -f app` | nginx access log + php-fpm + application errors |
| `docker compose -f compose.prod.yaml logs -f scheduler` | Each provider sync and its inserted/updated/skipped tally |
| `docker compose logs -f traefik` (in `/srv/traefik`) | Edge access log and ACME certificate events |

Cap Docker's own log growth in `/etc/docker/daemon.json`:

```json
{ "log-driver": "json-file", "log-opts": { "max-size": "10m", "max-file": "3" } }
```

### Backups

```bash
docker compose -f compose.prod.yaml exec -T mysql \
  mysqldump -u root -p"$DB_ROOT_PASSWORD" --single-transaction eventhub \
  | gzip > "/srv/backups/eventhub-$(date +%F).sql.gz"
```

Back up the **`jwt_keys` volume** too. Losing it regenerates the key pair on next boot and
invalidates every token already issued.

### Resource use

Roughly 1.2 GB with all four containers up (MySQL ~700 MB, app ~250 MB, scheduler
~150 MB, Redis ~50 MB). `compose.prod.yaml` sets a memory limit per service so one app
cannot starve the others; `docker stats` shows actual use.

---

## Notes for the other apps on this box

- **Router names must be unique.** `traefik.http.routers.<name>` collides silently across
  compose projects. This app uses `eventhub`.
- **Set `name:` in every compose file** (this one uses `name: eventhub`), or Compose
  derives the project name from the directory and two apps in similarly-named directories
  fight over container names.
- **Each app joins `edge` plus its own private network.** Only the HTTP-serving container
  goes on `edge`; databases stay on `internal`.
- **Trusted proxies.** Traefik reaches containers over a Docker bridge network in
  `172.16.0.0/12`, which this app trusts by default (`TRUSTED_PROXIES` overrides it).
  Without that the app sees every request as coming from the proxy and the per-IP login
  limiter collapses into one shared bucket. An app behind Cloudflare needs Cloudflare's
  published ranges added - never a wildcard on a host that is also reachable directly,
  since that lets anyone spoof their IP with a forged `X-Forwarded-For`.
- **HSTS is sent with `includeSubDomains`** and a two-year max-age. If any subdomain on
  this box is not served over HTTPS, browsers that have seen the header will refuse to
  reach it.

---

## What was verified before this was written

The production image was built and run against real MySQL and Redis, not just linted:

| Check | Result |
|-------|--------|
| Image builds | multi-stage, `--no-dev`, authoritative classmap |
| Boot sequence | cache warm, JWT keys generated, DB wait, migrations, nginx + php-fpm up |
| Docker healthcheck | `GET /health` 200 on the 30s interval |
| `GET /health` | `{"data":{"status":"ok"},"error":null}` |
| `GET /health/ready` | 403 without token, 200 with it, both dependencies `true` |
| Unauthenticated `/events` | `401 AUTHENTICATION_REQUIRED` in the envelope |
| `X-Powered-By` | absent |
| `GET /index2.php` | 404 - only `index.php` is executable |
| Scheduler role | ran `app:sync-events` on a loop, no migration race |
