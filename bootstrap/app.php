<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Course Catalog PDF pipeline
        $schedule->command('courses:pdf-stitch-batches --limit=10')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        $schedule->command('courses:pdf-process --limit=10')
            ->everyMinute()
            ->withoutOverlapping(20)
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // HSI crawl cache refresh (Phase 2)
        $schedule->command('hsi:crawl-pages --max=50')
            ->hourly()
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // Re-embed Algolia course + blog/news records weekly (Sunday 03:00)
        $schedule->command('hsi:embed-algolia')
            ->weeklyOn(0, '3:00')
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // CLD API sync Full Feed (02:00 on every 3rd day of the month: 1st, 4th, 7th, …)
        $schedule->command('cld:sync')
            ->cron('0 2 */3 * *')
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/scheduler.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Add middleware to all web routes to prevent indexing
        $middleware->web(append: [
            \App\Http\Middleware\PreventIndexing::class,
        ]);

        $middleware->alias([
            'internal.signature' => \App\Http\Middleware\VerifyInternalRequestSignature::class,
            'surveys-pdf.signature' => \App\Http\Middleware\VerifySurveysPdfRequestSignature::class,
            'training-assessment-pdf.cors' => \App\Http\Middleware\TrainingAssessmentPdfCors::class,
            'hsi-ai.cors' => \App\Http\Middleware\HsiAiCors::class,
            'cron.token' => \App\Http\Middleware\VerifyCronToken::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
