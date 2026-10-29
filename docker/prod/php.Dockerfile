# PHP-FPM runtime for production.
#
# The application code is NOT baked into this image: docker-compose.prod.yaml
# bind-mounts the checkout at /app so that storage/ stays on the host disk and
# can be backed up with a normal tar (see docs/OPERATIONS.md).
#
# Only the interpreter and its extensions live here, so a code deploy does not
# require a rebuild unless composer.json changes.

FROM php:8.4-fpm-alpine

# Extensions the application needs beyond the ones bundled with the official
# image: pdo_mysql (mysqlnd is already present), bcmath, intl, zip, opcache
# and pcntl (needed by schedule:work).
RUN apk add --no-cache \
        icu-libs \
        libzip \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Limits and opcache tuning. See docker/prod/php.ini.
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/99-app.ini

# The bind-mounted code belongs to the host deploy user while php-fpm workers
# run as www-data (uid 82 in Alpine). Keep a matching user in the image so
# `docker compose exec -u 82:82 app php artisan ...` behaves consistently.
RUN addgroup -g 1000 -S deploy 2>/dev/null || true \
    && adduser -u 1000 -S -G deploy deploy 2>/dev/null || true

WORKDIR /app

# php-fpm is the official image's default CMD; the scheduler service overrides
# it with `php artisan schedule:work`.
EXPOSE 9000
