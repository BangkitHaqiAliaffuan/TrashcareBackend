#!/bin/bash
set -e

echo "==> [Railway] Starting Laravel app initialization..."

# Use Railway's PORT env var, default to 80 if not set
APP_PORT="${PORT:-80}"
echo "==> [Railway] Listening on port: $APP_PORT"

# Replace port placeholder in nginx config
sed -i "s/__PORT__/$APP_PORT/g" /etc/nginx/nginx.conf

# Ensure storage and cache directories exist and are writable
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Run Laravel optimizations (config/route/view cache already done at build time via nixpacks)
# But if .env changes, we re-cache here
echo "==> [Railway] Clearing old cache..."
php artisan optimize:clear

echo "==> [Railway] Publishing Filament and Livewire assets..."
php artisan vendor:publish --tag=livewire:assets --force
php artisan vendor:publish --tag=filament-assets --force
php artisan filament:assets

echo "==> [Railway] Caching config and views (NO route cache - Filament incompatible)..."
php artisan config:cache
php artisan view:cache

echo "==> [Railway] Starting PHP-FPM in background..."
php-fpm -D

echo "==> [Railway] Starting Nginx..."
nginx -g "daemon off;"
