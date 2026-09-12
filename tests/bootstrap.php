<?php

/*
|--------------------------------------------------------------------------
| Test Database Safety Bootstrap
|--------------------------------------------------------------------------
|
| Test commands must never touch the developer, staging, or production
| database. Force the test process onto sqlite :memory: before Laravel boots.
|
*/

$safeEnvironment = [
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
];

foreach ($safeEnvironment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
