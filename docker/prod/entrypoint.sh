#!/bin/sh
set -e

# CONTAINER_ROLE decides what this container does with the shared image.
# Only "web" migrates: three containers racing the same migrator is how a deploy corrupts
# a schema.
ROLE="${CONTAINER_ROLE:-web}"

wait_for_database() {
    echo "[entrypoint] waiting for the database..."
    for attempt in $(seq 1 60); do
        if php bin/console dbal:run-sql 'SELECT 1' --quiet >/dev/null 2>&1; then
            echo "[entrypoint] database is up"
            return 0
        fi
        sleep 2
    done
    echo "[entrypoint] database did not become reachable in time" >&2
    return 1
}

ensure_jwt_keys() {
    if [ -f config/jwt/private.pem ]; then
        return 0
    fi

    if [ -z "${JWT_PASSPHRASE}" ]; then
        echo "[entrypoint] JWT_PASSPHRASE is empty and no key pair is mounted" >&2
        return 1
    fi

    echo "[entrypoint] generating the JWT key pair"
    mkdir -p config/jwt
    openssl genrsa -out config/jwt/private.pem -aes256 -passout "pass:${JWT_PASSPHRASE}" 4096
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin "pass:${JWT_PASSPHRASE}"
    chown app:app config/jwt/private.pem config/jwt/public.pem
}

# The caches embed environment values, so they are built at start against whatever .env the
# server provides rather than baked into an image that is built once.
php bin/console cache:clear --no-warmup
php bin/console cache:warmup
chown -R app:app var

ensure_jwt_keys

if [ "$ROLE" = "web" ]; then
    wait_for_database
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

exec "$@"
