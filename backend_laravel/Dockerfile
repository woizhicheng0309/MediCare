# PHP 8.5 runtime, development tools and application stages in one Dockerfile.
# runtime: production services; dev: Composer, Xdebug and live-code settings.
FROM php:8.5-fpm-trixie AS runtime

# Install nginx and Supervisor; remove unused default sites.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends nginx supervisor; \
    rm -rf /var/lib/apt/lists/* /etc/nginx/sites-available /etc/nginx/sites-enabled /var/www/html

# Compile the Demo PHP extensions and retain only their runtime libraries.
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends libfreetype-dev libjpeg62-turbo-dev libpng-dev libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd zip pdo_mysql; \
    apt-mark auto '.*' > /dev/null; \
    [ -z "$savedAptMark" ] || apt-mark manual $savedAptMark > /dev/null; \
    find /usr/local -type f -executable -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u \
        | xargs -r dpkg-query --search \
        | grep -v '^diversion by' \
        | cut -d: -f1 \
        | sort -u \
        | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*

# Use the production PHP configuration by default.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY base-image/php/conf.d/ $PHP_INI_DIR/conf.d/

# Use the Demo PHP-FPM configuration and Unix socket.
RUN rm -f /usr/local/etc/php-fpm.d/*.conf
COPY base-image/php/php-fpm.conf /usr/local/etc/php-fpm.conf

COPY base-image/nginx/nginx.conf /etc/nginx/nginx.conf
COPY base-image/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY base-image/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY base-image/supervisor/conf.d/ /etc/supervisor/conf.d/
COPY --chmod=755 base-image/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN mkdir -p /docker-entrypoint.d

WORKDIR /var/www
EXPOSE 8080
STOPSIGNAL SIGTERM
ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]


FROM runtime AS dev

# Composer needs unzip and git when installing dependencies.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Xdebug is installed for development and disabled unless XDEBUG_MODE is set.
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends $PHPIZE_DEPS; \
    pecl install xdebug; \
    docker-php-ext-enable xdebug; \
    rm -rf /tmp/pear; \
    apt-mark auto '.*' > /dev/null; \
    [ -z "$savedAptMark" ] || apt-mark manual $savedAptMark > /dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*

# Development settings enable live-code updates.
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY base-image/php/conf.d-dev/ $PHP_INI_DIR/conf.d/

# Build application dependencies with the same PHP extensions as production.
FROM runtime AS build
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY backend_laravel/composer.json backend_laravel/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY backend_laravel/ ./
# Create Laravel directories explicitly because Git does not preserve empty folders.
RUN mkdir -p resources/views storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-dev \
    && php artisan route:cache \
    && php artisan view:cache

# The final image contains application code and vendor, without Composer or dev tools.
FROM runtime AS prod
COPY --from=build --chown=www-data:www-data /var/www /var/www
COPY --chmod=755 backend_laravel/docker/entrypoint.d/ /docker-entrypoint.d/
