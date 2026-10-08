<?php

/*
| Vercel serverless entrypoint.
|
| The deployment filesystem is read-only except /tmp, so the SQLite database
| lives in /tmp and is migrated + seeded on each cold start. Data written at
| runtime (e.g. shared calculations) only lives as long as the instance; point
| DB_CONNECTION at a hosted database for persistence.
*/

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$database = env('DB_DATABASE', '/tmp/database.sqlite');

if (env('DB_CONNECTION', 'sqlite') === 'sqlite' && ! file_exists($database)) {
    touch($database);
    $app->make(ConsoleKernel::class)->call('migrate', ['--force' => true, '--seed' => true]);
}

$app->handleRequest(Request::capture());
