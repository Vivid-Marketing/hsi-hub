<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// Cloudways-friendly queue worker entrypoint.
// Intended to be hit by cron every minute, and to exit quickly.

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

// Drain the queue but don't run forever (Cloudways cron).
Artisan::call('queue:work', [
    '--stop-when-empty' => true,
    '--max-time' => 55,
    '--sleep' => 1,
    '--tries' => 1,
]);

echo Artisan::output();

