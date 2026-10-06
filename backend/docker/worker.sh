#!/bin/sh
set -eu

php artisan package:discover --ansi

exec php -d memory_limit=512M artisan queue:work redis --sleep=1 --tries=3 --backoff=5 --timeout=120 --memory=480
