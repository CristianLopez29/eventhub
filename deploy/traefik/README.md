# Shared Traefik proxy

Deployed **once per host**, not per application. Lives in `/srv/traefik`.

```bash
docker network create edge

sudo mkdir -p /srv/traefik && cd /srv/traefik
# copy this directory here (compose.yaml + dynamic/)
echo "ACME_EMAIL=you@example.com" > .env
docker compose up -d
```

## What it does

- Owns `:80` and `:443` — the only published ports on the box.
- Redirects HTTP to HTTPS and terminates TLS with Let's Encrypt (TLS-ALPN challenge).
- Discovers containers over the Docker socket, mounted **read-only**.
- Routes only containers labelled `traefik.enable=true`, so a new container is never
  exposed by accident.

## Adding another app

1. Join the `edge` network from the container that serves HTTP; keep its database and
   cache on a private network.
2. Give the router a **unique name**. `traefik.http.routers.<name>` collides silently
   across compose projects — this app uses `eventhub`.
3. Point the domain's A record at the host **before** starting the container. Traefik's
   TLS-ALPN challenge fails otherwise, and repeated failures hit Let's Encrypt rate limits.

## Certificates

They live in the `letsencrypt` volume (`acme.json`). Back it up, or a rebuild re-requests
every certificate and can hit the rate limit.
