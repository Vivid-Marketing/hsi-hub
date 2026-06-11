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

        // CLD API sync Full Feed
        $schedule->command('cld:sync --no-feedme')
            ->everyThreeDays()
            ->at('02:00')
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
            'cron.token' => \App\Http\Middleware\VerifyCronToken::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
