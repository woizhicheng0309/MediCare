<?php
// This is the entrypoint when http request come in -> nginx -> php-fpm -> execute public/index.php -> controllers
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// 載入 Composer 的 autoloader，讓 PHP 找得到 Laravel 與專案裡的 class
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php'; // 建立 Laravel 應用程式

$app->handleRequest(Request::capture()); // 取得這次 HTTP 請求，交給 Laravel 處理
