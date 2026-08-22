#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Wait for the database before touching the schema: on a fresh `compose up` the
# app container is ready well before MySQL finishes initialising.
echo "Waiting for the database..."
until php -r '
    $dsn = sprintf("mysql:host=%s;port=%s", getenv("DB_HOST") ?: "127.0.0.1", getenv("DB_PORT") ?: "3306");
    try { new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); }
    catch (Throwable $e) { exit(1); }
'; do
    sleep 2
done

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
