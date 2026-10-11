# PHP 8.5 + nginx + supervisord runtime image
# prod：正式環境用；dev：本機開發用，多了 composer、xdebug
FROM php:8.5-fpm-trixie AS prod

# nginx、supervisor。Debian 套件附的範例站台和 php 映像檔的 /var/www/html 用不到
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends nginx supervisor; \
    rm -rf /var/lib/apt/lists/* /etc/nginx/sites-available /etc/nginx/sites-enabled /var/www/html

# PHP 擴充：gd、zip、pdo_mysql。編譯完移除 -dev 套件，只留執行期需要的函式庫
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

# PHP 設定：php.ini-production，再用 conf.d/zz-base.ini 調整
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY php/conf.d/ $PHP_INI_DIR/conf.d/

# FPM 只用一個設定檔，專案要改就整份覆蓋
RUN rm -f /usr/local/etc/php-fpm.d/*.conf
COPY php/php-fpm.conf /usr/local/etc/php-fpm.conf

COPY nginx/nginx.conf /etc/nginx/nginx.conf
COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY supervisor/conf.d/ /etc/supervisor/conf.d/
COPY --chmod=755 entrypoint.sh /usr/local/bin/entrypoint.sh

RUN mkdir -p /docker-entrypoint.d

WORKDIR /var/www
EXPOSE 8080
STOPSIGNAL SIGTERM
ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]


FROM prod AS dev

# composer 解壓縮用 unzip，部分套件要用 git 下載
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# xdebug：預設關閉，要用時設環境變數 XDEBUG_MODE，例如 debug
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

# PHP 設定：php.ini-development，再用 conf.d/zz-dev.ini 調整
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY php/conf.d-dev/ $PHP_INI_DIR/conf.d/
