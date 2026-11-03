<?php

namespace App\Console\Commands;

use App\Services\CldApiService;
use Illuminate\Console\Command;

class CldExportCourseLanguagesBackfill extends Command
{
    protected $signature = 'cld:export-course-languages-backfill
                            {--output= : Output JSON path (default: storage/app/cld-api/backfill-course-languages.json)}
                            {--craft-export= : Craft courses export JSON path}';

    protected $description = 'Export CLD language (allLocales) backfill JSON for Craft courseLanguageCategories (scoped to enabled Craft courses)';

    public function handle(CldApiService $cldApi): int
    {
        ini_set('memory_limit', '2G');

        $outputPath = $this->option('output') ?: $cldApi->courseLanguagesBackfillExportPath();
        $craftExportPath = $this->option('craft-export') ?: null;

        $this->info('Reading enabled Craft course cldIds and fetching CLD catalog...');

        $count = $cldApi->writeCourseLanguagesBackfillExport($outputPath, $craftExportPath);

        if ($count === null) {
            $this->error('Failed to export course languages backfill.');

            return self::FAILURE;
        }

        $this->info("Backfill rows exported: {$count}");
        $this->info("Written to: {$outputPath}");

        return self::SUCCESS;
    }
}
