#!/bin/sh
set -eu
# Development uses live code and environment settings; production caches at startup.
if [ "${APP_ENV:-production}" = "local" ] || [ -f /var/www/.env ]; then
    echo "optimize: skipped for local development"
    exit 0
fi
su www-data -s /bin/sh -c "php /var/www/artisan optimize"
