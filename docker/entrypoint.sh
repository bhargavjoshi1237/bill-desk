#!/bin/sh
set -e

cd /var/www/html

# A persistent disk mounted over storage/ starts empty, so recreate the layout.
mkdir -p storage/app/private storage/app/public storage/logs \
    storage/framework/cache/data storage/framework/sessions storage/framework/views

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
fi

php artisan migrate --force

# One-off: creates the default logins and masters. Turn it off after the first deploy,
# because the seeder resets admin@example.com's password to "password" every time.
if [ "${SEED_DATABASE:-false}" = "true" ]; then
    php artisan db:seed --force
fi

php artisan optimize
php artisan filament:optimize

chown -R www-data:www-data storage bootstrap/cache
if [ -n "${DB_FILE:-}" ]; then
    chown -R www-data:www-data "$(dirname "$DB_FILE")"
fi

exec "$@"
