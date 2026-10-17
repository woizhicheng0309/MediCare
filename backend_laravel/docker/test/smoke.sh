#!/bin/sh
# Build and test the backend runtime using an isolated PHP fixture.
# Usage: backend_laravel/docker/test/smoke.sh [prod|dev]; optionally set PLATFORM.
set -u

target="${1:-prod}"
platform="${PLATFORM:-}"
case "$target" in
    prod|dev) ;;
    *) echo "Usage: $0 [prod|dev]" >&2; exit 1 ;;
esac
cd "$(dirname "$0")/../.." || exit 1

image="medicare-backend:smoke-$target"
name="medicare-runtime-smoke-$target-$$"
failed=0

check() { # check <description> <actual> <expected pattern>
    if printf '%s\n' "$2" | grep -q -- "$3"; then
        echo "PASS  $1"
    else
        echo "FAIL  $1 (expected: $3)"
        printf '%s\n' "$2" | head -5 | sed 's/^/      /'
        failed=1
    fi
}

check_not() { # check_not <description> <actual> <unexpected pattern>
    if printf '%s\n' "$2" | grep -q -- "$3"; then
        echo "FAIL  $1 (unexpected: $3)"
        failed=1
    else
        echo "PASS  $1"
    fi
}

docker build ${platform:+--platform "$platform"} --target "$target" -t "$image" . || exit 1
docker run -d --name "$name" ${platform:+--platform "$platform"} -p 127.0.0.1::8080 \
    -e FOO=bar -e TIMEZONE=Asia/Taipei \
    -v "$PWD/docker/test/www:/var/www:ro" \
    -v "$PWD/docker/test/hooks:/docker-entrypoint.d:ro" \
    "$image" > /dev/null || exit 1
trap 'docker rm -f "$name" > /dev/null 2>&1' EXIT
url="http://$(docker port "$name" 8080/tcp)"

for _ in $(seq 30); do
    curl -fs "$url/ping" > /dev/null && break
    sleep 1
done

echo "== nginx routing"
check "/ping returns pong from FPM" "$(curl -s "$url/ping")" "pong"
app="$(curl -s "$url/api/foo?x=1")"
check "Application routes reach index.php" "$app" "uri=/api/foo?x=1"
check "SCRIPT_NAME is /index.php" "$app" "script_name=/index.php"
check "Direct /index.php returns 404" "$(curl -s -o /dev/null -w '%{http_code}' "$url/index.php")" "404"
check "Static public files are served" "$(curl -s "$url/robots.txt")" "static-robots"
check "Dotfiles are blocked" "$(curl -s -o /dev/null -w '%{http_code}' "$url/.htaccess")" "403"
check "Disabled /pm-status reaches index.php" "$(curl -s "$url/pm-status")" "uri=/pm-status"
headers="$(curl -s -D - -o /dev/null -H 'Accept-Encoding: gzip' "$url/api/foo")"
check "gzip compression" "$headers" "Content-Encoding: gzip"
check "X-Frame-Options" "$headers" "X-Frame-Options: SAMEORIGIN"
check_not "Server header omits version" "$headers" "Server: nginx/"
check_not "No X-Powered-By header" "$headers" "X-Powered-By"

echo "== PHP settings (FPM)"
check "Workers receive environment variables" "$app" "env_foo=bar"
check "Workers run as www-data" "$app" "user=www-data"
check "TIMEZONE setting" "$app" "tz=Asia/Taipei"
check "memory_limit 128M" "$app" "memory_limit=128M"
check "post_max_size 65M" "$app" "post_max_size=65M"
check "upload_max_filesize 23M" "$app" "upload_max_filesize=23M"
check "max_execution_time 60" "$app" "max_execution_time=60"
check "allow_url_fopen disabled" "$app" "allow_url_fopen=off"
check "expose_php disabled" "$app" "expose_php=off"
check "OPcache enabled" "$app" "opcache.enable=on"
if [ "$target" = prod ]; then
    check "Production timestamp checks disabled" "$app" "opcache.validate_timestamps=off"
    check "Production errors hidden" "$app" "display_errors=off"
else
    check "Development timestamp checks enabled" "$app" "opcache.validate_timestamps=on"
    check "Development errors displayed" "$app" "display_errors=on"
fi

echo "== Extensions and tools"
modules="$(docker exec "$name" php -m)"
for module in gd zip pdo_mysql 'Zend OPcache'; do
    check "Extension $module" "$modules" "^$module\$"
done
gd="$(docker exec "$name" php -r 'var_export(gd_info());')"
check "gd supports FreeType" "$gd" "'FreeType Support' => true"
check "gd supports JPEG" "$gd" "'JPEG Support' => true"
if [ "$target" = dev ]; then
    check "composer" "$(docker exec "$name" composer --version 2>&1)" "Composer version"
    check "Xdebug loaded" "$modules" "^xdebug\$"
    check "Xdebug disabled by default" "$(docker exec "$name" php -r 'echo ini_get("xdebug.mode");')" "^off\$"
else
    check_not "No Composer in production" "$(docker exec "$name" sh -c 'command -v composer')" "composer"
fi

echo "== supervisord"
status="$(docker exec "$name" supervisorctl status)"
check "nginx running" "$status" "^nginx  *RUNNING"
check "PHP-FPM running" "$status" "^php-fpm  *RUNNING"
old_pid="$(docker exec "$name" cat /run/php-fpm.pid)"
docker exec "$name" sh -c "kill -TERM $old_pid"
sleep 3
check "FPM restarts after SIGTERM" "$(docker exec "$name" supervisorctl status php-fpm)" "RUNNING"
check_not "FPM has a new PID" "$(docker exec "$name" cat /run/php-fpm.pid)" "^$old_pid\$"
check "/ping works after restart" "$(curl -s "$url/ping")" "pong"

echo "== Logs (stdout only)"
logs="$(docker logs "$name" 2> /dev/null)"
check "Startup hook executed" "$logs" "hook: hello from 10-hello.sh"
check "Supervisor logs" "$logs" "supervisord started"
check "nginx access log" "$logs" "GET /api/foo?x=1"
check "Worker stderr appears without prefix" "$logs" "^smoke-test: hello from worker"
check_not "/ping omitted from access logs" "$logs" "GET /ping"

echo "== Shutdown"
start="$(date +%s)"
docker stop "$name" > /dev/null
check "docker stop completes within 10 seconds" "$(($(date +%s) - start))" "^[0-9]\$"

[ "$failed" = 0 ] && echo "All checks passed" || echo "Some checks failed"
exit "$failed"
