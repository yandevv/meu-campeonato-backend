#!/bin/sh
set -e

echo "Running migrations..."
php artisan migrate --force --no-interaction

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan event:cache

echo "Starting services..."
exec "$@"
