<?php

namespace App\Console\Commands;

use App\Services\CldApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CldExportCourseOutlineBackfill extends Command
{
    protected $signature = 'cld:export-course-outline-backfill
                            {--output= : Output JSON path (default: storage/app/cld-api/backfill-course-outline.json)}
                            {--craft-export= : Craft courses export JSON path}
                            {--lesson-ids-file= : Optional text file of numeric lesson IDs (one per line) to limit the export}';

    protected $description = 'Export pipe-delimited courseOutline backfill JSON for Craft (scoped to enabled Craft courses)';

    public function handle(CldApiService $cldApi): int
    {
        ini_set('memory_limit', '2G');

        $outputPath = $this->option('output') ?: $cldApi->courseOutlineBackfillExportPath();
        $craftExportPath = $this->option('craft-export') ?: null;
        $lessonIds = $this->lessonIdsFromOption();

        if ($lessonIds === false) {
            return self::FAILURE;
        }

        if ($lessonIds !== null) {
            $this->info('Fetching LessonSection outlines for '.count($lessonIds).' lesson IDs from --lesson-ids-file...');
        } else {
            $this->info('Reading enabled Craft course cldIds and fetching LessonSection outlines...');
        }

        $count = $cldApi->writeCourseOutlineBackfillExport($outputPath, $craftExportPath, $lessonIds);

        if ($count === null) {
            $this->error('Failed to export courseOutline backfill.');

            return self::FAILURE;
        }

        $this->info("Backfill rows exported: {$count}");
        $this->info("Written to: {$outputPath}");

        return self::SUCCESS;
    }

    /**
     * @return list<int>|null|false Null = no filter; false = invalid file
     */
    private function lessonIdsFromOption(): array|null|false
    {
        $path = $this->option('lesson-ids-file');
        if ($path === null || $path === '') {
            return null;
        }

        if (! File::exists($path)) {
            $this->error("Lesson IDs file not found: {$path}");

            return false;
        }

        $ids = [];
        foreach (preg_split('/\R/', File::get($path)) as $line) {
            $line = trim((string) $line);
            if ($line === '' || ! ctype_digit($line)) {
                continue;
            }
            $ids[(int) $line] = true;
        }

        $list = array_map('intval', array_keys($ids));
        sort($list);

        if ($list === []) {
            $this->error("No numeric lesson IDs found in: {$path}");

            return false;
        }

        return $list;
    }
}
