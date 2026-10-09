#!/bin/sh
# 建置並實測 base image
# 用法：test/smoke.sh [prod|dev]，要測 amd64 時加 PLATFORM=linux/amd64
set -u

target="${1:-prod}"
platform="${PLATFORM:-}"
cd "$(dirname "$0")/.." || exit 1

image="base-image:smoke-$target"
name="base-image-smoke-$target"
url="http://localhost:18080"
failed=0

check() { # check <說明> <實際值> <預期包含的字串>
    if printf '%s\n' "$2" | grep -q -- "$3"; then
        echo "PASS  $1"
    else
        echo "FAIL  $1（預期包含：$3）"
        printf '%s\n' "$2" | head -5 | sed 's/^/      /'
        failed=1
    fi
}

check_not() { # check_not <說明> <實際值> <不應出現的字串>
    if printf '%s\n' "$2" | grep -q -- "$3"; then
        echo "FAIL  $1（不應包含：$3）"
        failed=1
    else
        echo "PASS  $1"
    fi
}

docker build ${platform:+--platform "$platform"} --target "$target" -t "$image" . || exit 1
docker rm -f "$name" > /dev/null 2>&1
docker run -d --name "$name" ${platform:+--platform "$platform"} -p 18080:8080 \
    -e FOO=bar -e TIMEZONE=Asia/Taipei \
    -v "$PWD/test/www:/var/www:ro" \
    -v "$PWD/test/hooks:/docker-entrypoint.d:ro" \
    "$image" > /dev/null || exit 1
trap 'docker rm -f "$name" > /dev/null 2>&1' EXIT

for _ in $(seq 30); do
    curl -fs "$url/ping" > /dev/null && break
    sleep 1
done

echo "== nginx 路由"
check "/ping 由 FPM 回 pong" "$(curl -s "$url/ping")" "pong"
app="$(curl -s "$url/api/foo?x=1")"
check "其他路徑交給 index.php" "$app" "uri=/api/foo?x=1"
check "SCRIPT_NAME 是 /index.php" "$app" "script_name=/index.php"
check "/index.php 回 404" "$(curl -s -o /dev/null -w '%{http_code}' "$url/index.php")" "404"
check "public/ 下的檔案直接回" "$(curl -s "$url/robots.txt")" "static-robots"
check "擋 . 開頭的檔案" "$(curl -s -o /dev/null -w '%{http_code}' "$url/.htaccess")" "403"
check "/pm-status 沒開，交給 index.php" "$(curl -s "$url/pm-status")" "uri=/pm-status"
headers="$(curl -s -D - -o /dev/null -H 'Accept-Encoding: gzip' "$url/api/foo")"
check "gzip 壓縮" "$headers" "Content-Encoding: gzip"
check "X-Frame-Options" "$headers" "X-Frame-Options: SAMEORIGIN"
check_not "Server 標頭不帶版本" "$headers" "Server: nginx/"
check_not "沒有 X-Powered-By" "$headers" "X-Powered-By"

echo "== PHP 設定（FPM）"
check "worker 讀得到環境變數" "$app" "env_foo=bar"
check "worker 以 www-data 執行" "$app" "user=www-data"
check "時區 TIMEZONE" "$app" "tz=Asia/Taipei"
check "memory_limit 128M" "$app" "memory_limit=128M"
check "post_max_size 65M" "$app" "post_max_size=65M"
check "upload_max_filesize 23M" "$app" "upload_max_filesize=23M"
check "max_execution_time 60" "$app" "max_execution_time=60"
check "allow_url_fopen 關閉" "$app" "allow_url_fopen=off"
check "expose_php 關閉" "$app" "expose_php=off"
check "OPcache 啟用" "$app" "opcache.enable=on"
if [ "$target" = prod ]; then
    check "不檢查檔案修改時間" "$app" "opcache.validate_timestamps=off"
    check "不顯示錯誤" "$app" "display_errors=off"
else
    check "檢查檔案修改時間" "$app" "opcache.validate_timestamps=on"
    check "顯示錯誤" "$app" "display_errors=on"
fi

echo "== 擴充與工具"
modules="$(docker exec "$name" php -m)"
for module in gd zip pdo_mysql 'Zend OPcache'; do
    check "擴充 $module" "$modules" "^$module\$"
done
gd="$(docker exec "$name" php -r 'var_export(gd_info());')"
check "gd 支援 FreeType" "$gd" "'FreeType Support' => true"
check "gd 支援 JPEG" "$gd" "'JPEG Support' => true"
if [ "$target" = dev ]; then
    check "composer" "$(docker exec "$name" composer --version 2>&1)" "Composer version"
    check "xdebug 已載入" "$modules" "^xdebug\$"
    check "xdebug 預設關閉" "$(docker exec "$name" php -r 'echo ini_get("xdebug.mode");')" "^off\$"
else
    check_not "沒有 composer" "$(docker exec "$name" sh -c 'command -v composer')" "composer"
fi

echo "== supervisord"
status="$(docker exec "$name" supervisorctl status)"
check "nginx 執行中" "$status" "^nginx  *RUNNING"
check "php-fpm 執行中" "$status" "^php-fpm  *RUNNING"
old_pid="$(docker exec "$name" cat /run/php-fpm.pid)"
docker exec "$name" sh -c "kill -TERM $old_pid"
sleep 3
check "FPM 被 SIGTERM 後自動重啟" "$(docker exec "$name" supervisorctl status php-fpm)" "RUNNING"
check_not "FPM 換了新的 pid" "$(docker exec "$name" cat /run/php-fpm.pid)" "^$old_pid\$"
check "重啟後 /ping 正常" "$(curl -s "$url/ping")" "pong"

echo "== log（只看 stdout）"
logs="$(docker logs "$name" 2> /dev/null)"
check "啟動 hook 有執行" "$logs" "hook: hello from 10-hello.sh"
check "supervisord 自己的 log" "$logs" "supervisord started"
check "nginx 存取紀錄" "$logs" "GET /api/foo?x=1"
check "worker 寫到 stderr 的內容，沒有前綴" "$logs" "^smoke-test: hello from worker"
check_not "/ping 不記存取紀錄" "$logs" "GET /ping"

echo "== 停止"
start="$(date +%s)"
docker stop "$name" > /dev/null
check "docker stop 在 10 秒內結束" "$(($(date +%s) - start))" "^[0-9]\$"

[ "$failed" = 0 ] && echo "全部通過" || echo "有項目失敗"
exit "$failed"
