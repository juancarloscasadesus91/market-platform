#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

chmod -R ug+rwX bootstrap/cache storage || true

if [ ! -f .env ]; then
    cp .env.example .env
fi

set_env_value() {
    key="$1"
    value="$2"

    if grep -q "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        printf '\n%s=%s\n' "${key}" "${value}" >> .env
    fi
}

if [ "${SYNC_DOCKER_ENV:-true}" = "true" ]; then
    set_env_value APP_URL "${APP_URL:-http://localhost:8000}"
    set_env_value DB_CONNECTION "${DB_CONNECTION:-mysql}"
    set_env_value DB_HOST "${DB_HOST:-mysql}"
    set_env_value DB_PORT "${DB_PORT:-3306}"
    set_env_value DB_DATABASE "${DB_DATABASE:-market_platform}"
    set_env_value DB_USERNAME "${DB_USERNAME:-market}"
    set_env_value DB_PASSWORD "${DB_PASSWORD:-market}"
    set_env_value REDIS_CLIENT "${REDIS_CLIENT:-predis}"
    set_env_value REDIS_HOST "${REDIS_HOST:-redis}"
    set_env_value REDIS_PORT "${REDIS_PORT:-6379}"
    set_env_value REDIS_URL "${REDIS_URL:-redis://redis:6379}"
    set_env_value SESSION_DRIVER "${SESSION_DRIVER:-redis}"
    set_env_value CACHE_STORE "${CACHE_STORE:-redis}"
    set_env_value QUEUE_CONNECTION "${QUEUE_CONNECTION:-redis}"
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -d node_modules ] || [ ! -f node_modules/.package-lock.json ]; then
    if [ -f package-lock.json ]; then
        npm ci
    else
        npm install
    fi
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --no-interaction
fi

php artisan config:clear --no-interaction || true

if [ "${DB_CONNECTION:-}" = "mysql" ] && [ -n "${DB_HOST:-}" ]; then
    until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-root}" -p"${DB_PASSWORD:-}" --silent; do
        echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ ! -e public/storage ]; then
    php artisan storage:link --no-interaction || true
fi

exec "$@"
