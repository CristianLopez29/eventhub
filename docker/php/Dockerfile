FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    linux-headers \
    pcre-dev \
    ${PHPIZE_DEPS} \
    git \
    zip \
    unzip \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    mariadb-dev \
    bash

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install \
    intl \
    pdo \
    pdo_mysql \
    zip \
    bcmath \
    mbstring \
    xml \
    opcache

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Enable OPcache for production performance
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Configure PHP-FPM for high concurrency and env passing
RUN echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_children = 50" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.start_servers = 10" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.min_spare_servers = 5" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_spare_servers = 20" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_requests = 500" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

# Cleanup build dependencies
RUN apk del pcre-dev ${PHPIZE_DEPS}

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create a non-root user matching common host UID/GID (1000)
RUN addgroup -g 1000 appgroup && \
    adduser -u 1000 -G appgroup -D appuser

# Configure Composer environment
ENV COMPOSER_HOME=/home/appuser/.composer
RUN mkdir -p $COMPOSER_HOME/cache && \
    chown -R appuser:appgroup /home/appuser

# Configure working directory
WORKDIR /app

# Ensure the app directory is writable by appuser (for bind-mounted volumes)
RUN chown appuser:appgroup /app

USER appuser

# Configure Git to trust the /app directory
RUN git config --global --add safe.directory /app

EXPOSE 9000

CMD ["php-fpm"]
