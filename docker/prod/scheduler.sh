#!/bin/sh
set -e

# The provider sync on a fixed interval. A cron daemon inside a container needs its own
# supervision and swallows the command's output; a sleep loop in PID 1 restarts with the
# container and logs to stdout like every other service here.
INTERVAL="${SYNC_INTERVAL_SECONDS:-3600}"

echo "[scheduler] syncing provider events every ${INTERVAL}s"

while true; do
    php /app/bin/console app:sync-events || echo "[scheduler] sync failed, retrying next cycle" >&2
    sleep "${INTERVAL}"
done
