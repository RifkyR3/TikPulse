#!/bin/sh
set -e

echo "==> [TikPulse] Starting container entrypoint..."

# Remove any stale bootstrap cache (packages.php, services.php, config.php)
rm -f /var/www/html/bootstrap/cache/packages.php \
      /var/www/html/bootstrap/cache/services.php \
      /var/www/html/bootstrap/cache/config.php \
      /var/www/html/bootstrap/cache/routes*.php

# Discover installed packages
php artisan package:discover --ansi || true

# Wait for PostgreSQL database if DB_HOST is set
if [ -n "$DB_HOST" ]; then
    echo "==> [TikPulse] Waiting for PostgreSQL database at ${DB_HOST}:${DB_PORT:-5432}..."
    until php -r "
        try {
            new PDO(
                'pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: '5432').';dbname='.getenv('DB_DATABASE'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    "; do
        echo "    PostgreSQL is unavailable - sleeping for 2 seconds..."
        sleep 2
    done
    echo "==> [TikPulse] PostgreSQL connection established successfully!"
fi

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    echo "==> [TikPulse] Generating application key..."
    php artisan key:generate --force
fi

# Run Database Migrations
echo "==> [TikPulse] Running database migrations..."
php artisan migrate --force

# Run Database Seeders (first-time admin creation)
if [ "${RUN_SEEDER:-true}" = "true" ]; then
    echo "==> [TikPulse] Seeding initial database records..."
    php artisan db:seed --force || true
fi

# Optional automated dummy MikroTik setup in dev environment
if [ "${MIKROTIK_AUTO_SETUP:-false}" = "true" ]; then
    echo "==> [TikPulse] MIKROTIK_AUTO_SETUP is enabled. Starting background provisioner..."
    (
        sleep 5
        php artisan mikrotik:setup-dummy \
            --host="${MIKROTIK_HOST:-mikrotik-dummy}" \
            --port="${MIKROTIK_PORT:-8728}" \
            --username="${MIKROTIK_USER:-admin}" \
            --password="${MIKROTIK_PASS:-admin123}" \
            --timeout=90 || true
    ) &
fi

# Production vs Development optimizations
if [ "$APP_ENV" = "production" ]; then
    echo "==> [TikPulse] Production environment detected: caching configuration and routes..."
    php artisan config:cache || true
    php artisan route:cache || true
else
    echo "==> [TikPulse] Development environment: enabling hot code revalidation..."
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
    mkdir -p /usr/local/etc/php/conf.d
    echo "opcache.validate_timestamps=1" > /usr/local/etc/php/conf.d/docker-php-ext-opcache-dev.ini
    echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache-dev.ini
fi

echo "==> [TikPulse] Application ready! Executing: $@"
exec "$@"
