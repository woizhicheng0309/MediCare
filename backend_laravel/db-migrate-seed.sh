#!/bin/sh
set -eu
# Compose waits for MySQL health before running this one-shot initialization job.
su www-data -s /bin/sh -c "php /var/www/artisan migrate --force"
# Sample data belongs only to local development, never to production deployment.
if [ "${APP_ENV:-production}" = "local" ]; then
    su www-data -s /bin/sh -c "php /var/www/artisan db:seed --force"
fi
