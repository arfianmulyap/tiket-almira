#!/usr/bin/env bash
#
# Release script for the production Docker stack.
#
# Run on the server from the checkout:
#
#   ./docker/prod/deploy.sh
#
# Assumes:
#   - the checkout is owned by uid/gid 82 (www-data in Alpine), so php-fpm and
#     the artisan commands below write files as the same user;
#   - .env exists and is readable by uid 82;
#   - DNS for APP_DOMAIN points at this server.

set -euo pipefail

cd "$(dirname "$0")/../.."

COMPOSE=(docker compose -f compose.prod.yaml)
RUN=("${COMPOSE[@]}" run --rm -u 82:82 --env HOME=/tmp --env COMPOSER_HOME=/tmp/composer app)

echo "==> Pulling code"
git config --global --add safe.directory "$PWD" 2>/dev/null || true
git pull --ff-only

echo "==> Building PHP image"
"${COMPOSE[@]}" build app

echo "==> Installing PHP dependencies"
"${RUN[@]}" composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Building frontend assets"
"${COMPOSE[@]}" --profile build run --rm -u 82:82 --env HOME=/tmp assets

echo "==> Running migrations"
"${RUN[@]}" php artisan migrate --force

echo "==> Ensuring permission catalogues exist"
"${RUN[@]}" php artisan db:seed --class=RolePermissionSeeder --force
"${RUN[@]}" php artisan db:seed --class=PaymentMethodSeeder --force

echo "==> Linking public storage"
"${RUN[@]}" php artisan storage:link || true

echo "==> Caching configuration, routes and views"
"${RUN[@]}" php artisan config:cache
"${RUN[@]}" php artisan route:cache
"${RUN[@]}" php artisan view:cache

echo "==> Restarting containers"
"${COMPOSE[@]}" up -d --remove-orphans

echo "==> Health check"
"${COMPOSE[@]}" exec -T app php artisan about --only=environment
curl -fsS "https://${APP_DOMAIN:-tiket.almira.my.id}/up" >/dev/null && echo "OK: /up returned 200"
