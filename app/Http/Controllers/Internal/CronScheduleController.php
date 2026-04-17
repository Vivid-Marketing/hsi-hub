<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class CronScheduleController extends Controller
{
    public function run(): JsonResponse
    {
        $exitCode = Artisan::call('schedule:run');
        $output = Artisan::output();

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => $output,
        ]);
    }
}

