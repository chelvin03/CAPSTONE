<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// BEGIN temporary reservation entry diagnostic (remove after hosting diagnosis).
$diagnosticPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$diagnosticMethod = $_SERVER['REQUEST_METHOD'] ?? '';
if (($diagnosticMethod === 'POST' && $diagnosticPath === '/reserve')
    || ($diagnosticMethod === 'GET' && $diagnosticPath === '/reserve/request')) {
    try {
        $diagnosticId = bin2hex(random_bytes(8));
        $diagnosticWritten = false;
        if (is_dir(__DIR__.'/../storage/logs') && is_writable(__DIR__.'/../storage/logs')) {
            $diagnosticWritten = @file_put_contents(__DIR__.'/../storage/logs/reservation-entry.log', json_encode([
            'time_utc' => gmdate('c'), 'diagnostic_id' => $diagnosticId,
            'stage' => 'php_entry', 'method' => $diagnosticMethod, 'path' => $diagnosticPath,
            ], JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        if (! headers_sent()) {
            header('X-MCST-PHP-Reached: yes');
            header('X-MCST-Diagnostic-ID: '.$diagnosticId);
            header('X-MCST-Trace-Written: '.($diagnosticWritten === false ? 'no' : 'yes'));
        }
    } catch (\Throwable $diagnosticException) {
        // Diagnostics must never prevent normal request handling.
    }
}
unset($diagnosticPath, $diagnosticMethod, $diagnosticId, $diagnosticWritten, $diagnosticException);
// END temporary reservation entry diagnostic.

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
