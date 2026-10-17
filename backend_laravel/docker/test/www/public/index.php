<?php

// Return request details and effective FPM settings for runtime smoke tests.
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

// Write to php://stderr, as Laravel does with LOG_CHANNEL=stderr.
file_put_contents('php://stderr', "smoke-test: hello from worker\n");
