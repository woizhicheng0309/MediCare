<?php

// test/smoke.sh 用：印出請求內容和 FPM 實際生效的 PHP 設定
header('Content-Type: text/plain');

$flag = fn (string $key) => ini_get($key) ? 'on' : 'off';

echo 'uri=' . $_SERVER['REQUEST_URI'] . "\n";
echo 'script_name=' . $_SERVER['SCRIPT_NAME'] . "\n";
echo 'env_foo=' . getenv('FOO') . "\n";
echo 'user=' . posix_getpwuid(posix_geteuid())['name'] . "\n";
echo 'tz=' . date_default_timezone_get() . "\n";
foreach (['memory_limit', 'post_max_size', 'upload_max_filesize', 'max_execution_time'] as $key) {
    echo "$key=" . ini_get($key) . "\n";
}
foreach (['allow_url_fopen', 'expose_php', 'display_errors', 'opcache.enable', 'opcache.validate_timestamps'] as $key) {
    echo "$key=" . $flag($key) . "\n";
}

// 和 Laravel 的 LOG_CHANNEL=stderr 一樣寫到 php://stderr
file_put_contents('php://stderr', "smoke-test: hello from worker\n");
