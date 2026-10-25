#!/bin/sh
set -e

cd /var/www/html

# Laravel needs "base64:" + 32 random bytes. Accept a bare base64 value too (what Render's
# "Generate" button produces) and refuse to start on anything else, instead of serving 500s.
case "${APP_KEY:-}" in
    base64:*) KEY_BYTES=$(printf '%s' "${APP_KEY#base64:}" | base64 -d 2>/dev/null | wc -c) ;;
    "") KEY_BYTES=0 ;;
    *)
        KEY_BYTES=$(printf '%s' "$APP_KEY" | base64 -d 2>/dev/null | wc -c)
        [ "$KEY_BYTES" -eq 32 ] && export APP_KEY="base64:$APP_KEY"
        ;;
esac
if [ "$KEY_BYTES" -ne 32 ]; then
    echo "APP_KEY is missing or invalid. Set it to the output of 'php artisan key:generate --show'" >&2
    echo "(it looks like base64:xxxxxxxx...=), then redeploy." >&2
    exit 1
fi

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
