<?php

namespace App\Console\Commands;

use App\Services\CldApiService;
use Illuminate\Console\Command;

class CldExportCourseOutlineBackfill extends Command
{
    protected $signature = 'cld:export-course-outline-backfill
                            {--output= : Output JSON path (default: storage/app/cld-api/backfill-course-outline.json)}
                            {--craft-export= : Craft courses export JSON path}';

    protected $description = 'Export pipe-delimited courseOutline backfill JSON for Craft (scoped to enabled Craft courses)';

    public function handle(CldApiService $cldApi): int
    {
        ini_set('memory_limit', '2G');

        $outputPath = $this->option('output') ?: $cldApi->courseOutlineBackfillExportPath();
        $craftExportPath = $this->option('craft-export') ?: null;

        $this->info('Reading enabled Craft course cldIds and fetching LessonSection outlines...');

        $count = $cldApi->writeCourseOutlineBackfillExport($outputPath, $craftExportPath);

        if ($count === null) {
            $this->error('Failed to export courseOutline backfill.');

            return self::FAILURE;
        }

        $this->info("Backfill rows exported: {$count}");
        $this->info("Written to: {$outputPath}");

        return self::SUCCESS;
    }
}
