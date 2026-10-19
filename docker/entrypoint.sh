#!/bin/sh
set -e

cd /var/www

# A free Render instance starts from the image's filesystem, so the writable
# storage tree is recreated on every boot.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/exports \
    storage/logs \
    bootstrap/cache

php artisan config:cache

# Seed the first admin when the environment names one. The seeder refuses once
# any staff row exists; that refusal is expected on every later boot, so it must
# not stop the container.
if [ -n "${STAFF_ADMIN_EMAIL:-}" ]; then
    php artisan staff:seed --admin || true
fi

# The sweeps live in the scheduler. It shares this container because the free
# plan has no separate worker, and the export-backup file must stay on the same
# filesystem as the admin download that reads it.
php artisan schedule:work &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
