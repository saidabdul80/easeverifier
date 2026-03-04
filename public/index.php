<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Serve ACME HTTP-01 challenge files early, regardless of Laravel route cache.
$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$acmePrefix = '/.well-known/acme-challenge/';

if (str_starts_with($requestPath, $acmePrefix)) {
    $token = substr($requestPath, strlen($acmePrefix));

    if ($token !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $token) === 1) {
        $challengePath = __DIR__.'/.well-known/acme-challenge/'.$token;

        if (is_file($challengePath)) {
            header('Content-Type: text/plain; charset=UTF-8');
            readfile($challengePath);
            exit;
        }
    }

    http_response_code(404);
    echo 'Not Found';
    exit;
}

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
