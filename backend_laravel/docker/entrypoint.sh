#!/bin/sh
set -eu

# 本機掛載專案目錄時，把 www-data 的 uid、gid 改成和 /var/www 的擁有者一致，避免權限錯亂
uid="$(stat -c %u /var/www)"
gid="$(stat -c %g /var/www)"
if [ "$uid" != 0 ] && [ "$uid" != "$(id -u www-data)" ]; then
    usermod --non-unique --uid "$uid" www-data
    chown -R www-data /var/lib/nginx
fi
if [ "$gid" != 0 ] && [ "$gid" != "$(id -g www-data)" ]; then
    groupmod --non-unique --gid "$gid" www-data
    chgrp -R www-data /var/lib/nginx
fi

# 時區，不設就是 UTC
if [ -n "${TIMEZONE:-}" ]; then
    if [ ! -f "/usr/share/zoneinfo/$TIMEZONE" ]; then
        echo "entrypoint: unknown TIMEZONE $TIMEZONE" >&2
        exit 1
    fi
    ln -sf "/usr/share/zoneinfo/$TIMEZONE" /etc/localtime
    echo "$TIMEZONE" > /etc/timezone
    echo "date.timezone = $TIMEZONE" > "$PHP_INI_DIR/conf.d/zz-timezone.ini"
fi

# 專案放在 /docker-entrypoint.d 的啟動指令，依檔名順序用 sh 執行，任何一個失敗就停止啟動
for hook in /docker-entrypoint.d/*.sh; do
    [ -e "$hook" ] || continue
    echo "entrypoint: running $hook"
    sh "$hook"
done

exec "$@"
