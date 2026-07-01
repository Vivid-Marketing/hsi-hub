<?php

namespace App\Console\Commands;

use App\Services\CldApiService;
use Illuminate\Console\Command;

class CldExportEmeaCourses extends Command
{
    protected $signature = 'cld:export-emea-courses
                            {--output= : Output JSON path (default: storage/app/cld-api/emea-courses.json)}';

    protected $description = 'Export CLD courses affiliated with the EMEA Web Catalog to JSON';

    public function handle(CldApiService $cldApi): int
    {
        ini_set('memory_limit', '2G');

        $outputPath = $this->option('output') ?: $cldApi->emeaCoursesExportPath();

        $this->info('Fetching full CLD catalog from API (this may take a minute)...');

        $count = $cldApi->writeEmeaCoursesExport($outputPath);

        if ($count === null) {
            $this->error('Failed to export EMEA courses from CLD API.');

            return self::FAILURE;
        }

        $this->info("EMEA courses exported: {$count}");
        $this->info("Written to: {$outputPath}");

        return self::SUCCESS;
    }
}
