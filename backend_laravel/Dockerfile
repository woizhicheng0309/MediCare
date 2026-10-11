FROM php:8.5-cli

# Install download tools and the PHP driver for MySQL.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Copy Composer from its official image.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Cache dependencies until composer.json or composer.lock changes.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

# Run Laravel discovery after application files are available.
COPY . .
RUN composer dump-autoload --optimize

# Listen on every container interface so Docker can expose the server.
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]