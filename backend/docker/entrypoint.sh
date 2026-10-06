#!/bin/sh
set -eu

php artisan package:discover --ansi

attempt=0
until php artisan migrate --force --seed; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "A migração não concluiu." >&2
        exit 1
    fi
    sleep 2
done

exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
