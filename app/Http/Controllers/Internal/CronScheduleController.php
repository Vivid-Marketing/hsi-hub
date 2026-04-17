<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class CronScheduleController extends Controller
{
    public function run(): JsonResponse
    {
        // Some production hosts disable proc_open, which breaks Laravel's scheduler
        // (it uses Symfony Process). In that environment we still want a single
        // "cron hit" to run our scheduled tasks, so we fall back to calling the
        // commands directly.
        try {
            $exitCode = Artisan::call('schedule:run');
            $output = Artisan::output();

            return response()->json([
                'ok' => $exitCode === 0,
                'mode' => 'schedule:run',
                'exit_code' => $exitCode,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            report($e);

            $results = [];
            $overallOk = true;

            foreach ([
                'courses:pdf-stitch-batches --limit=10',
                'courses:pdf-process --limit=10',
            ] as $command) {
                try {
                    $code = Artisan::call($command);
                    $results[] = [
                        'command' => $command,
                        'exit_code' => $code,
                        'output' => Artisan::output(),
                    ];
                    $overallOk = $overallOk && ($code === 0);
                } catch (\Throwable $inner) {
                    report($inner);
                    $results[] = [
                        'command' => $command,
                        'exit_code' => 1,
                        'output' => $inner->getMessage(),
                    ];
                    $overallOk = false;
                }
            }

            return response()->json([
                'ok' => $overallOk,
                'mode' => 'direct',
                'error' => $e->getMessage(),
                'results' => $results,
            ], $overallOk ? 200 : 500);
        }
    }
}

