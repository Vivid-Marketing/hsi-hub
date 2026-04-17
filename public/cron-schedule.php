<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// Simple HTTP/CLI entrypoint for Cloudways cron.
// Secure via CRON_TOKEN; required for web requests.

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$token = (string) config('cron.token', '');
$provided = (string) ($_GET['token'] ?? '');

if (PHP_SAPI !== 'cli') {
    if ($token === '' || ! hash_equals($token, $provided)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

Artisan::call('schedule:run');

echo Artisan::output();
