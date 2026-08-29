# syntax=docker/dockerfile:1

# --- Stage 1: dependencies -------------------------------------------------
# Installed against the same PHP that will run them, so a platform mismatch fails the
# build rather than production.
FROM php:8.4-fpm-alpine AS vendor

RUN apk add --no-cache git unzip icu-dev libzip-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl zip bcmath >/dev/null

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock* ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && rm -rf tests docker/provider .github


# --- Stage 2: runtime ------------------------------------------------------
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx \
        supervisor \
        icu-libs \
        libzip \
        oniguruma \
        libxml2 \
        mysql-client \
    && apk add --no-cache --virtual .build-deps \
        ${PHPIZE_DEPS} linux-headers pcre-dev icu-dev libzip-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl pdo pdo_mysql zip bcmath opcache >/dev/null \
    && pecl install redis >/dev/null \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisord.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /app

COPY --from=vendor /app /app

# nginx and php-fpm both run as this user; only var/ and config/jwt/ are ever written to.
RUN addgroup -g 1000 app \
    && adduser -u 1000 -G app -D -H app \
    && mkdir -p var/cache var/log config/jwt /var/lib/nginx/tmp /var/log/nginx \
    && chown -R app:app var config/jwt /var/lib/nginx /var/log/nginx

EXPOSE 8080

# The container is healthy when PHP answers, which is what Traefik should route to.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/health >/dev/null || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
