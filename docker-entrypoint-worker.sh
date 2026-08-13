#!/bin/bash
set -e

mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache || true
touch storage/logs/laravel.log || true
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

php artisan config:clear || true
php artisan cache:clear || true

mkdir -p /run/supervisor
chmod 755 /run /run/supervisor

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
