<?php

namespace App\Console\Commands;

use App\Services\CldApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CldExportEmeaCourses extends Command
{
    protected $signature = 'cld:export-emea-courses
                            {--output= : Output JSON path (default: storage/app/cld-api/emea-courses.json)}';

    protected $description = 'Export CLD courses affiliated with the EMEA Web Catalog to JSON';

    public function handle(CldApiService $cldApi): int
    {
        ini_set('memory_limit', '2G');

        $outputPath = $this->option('output')
            ?: storage_path('app/cld-api/emea-courses.json');

        $this->info('Fetching full CLD catalog from API (this may take a minute)...');

        $token = $cldApi->getBearerToken();
        $courses = $cldApi->getAllCatalogCourses($token);

        if ($courses === null) {
            $this->error('Failed to fetch catalog from CLD API.');

            return self::FAILURE;
        }

        $total = count($courses);
        $this->info("Fetched {$total} courses. Filtering for EMEA Web Catalog affiliation...");

        $emeaCourses = [];
        foreach ($courses as $course) {
            if (! is_array($course) || ! $cldApi->courseHasEmeaWebCatalogAffiliation($course)) {
                continue;
            }

            $emeaCourses[] = $cldApi->courseToEmeaExportRow($course);
        }

        usort($emeaCourses, static fn (array $a, array $b) => ($a['LessonID'] ?? 0) <=> ($b['LessonID'] ?? 0));

        $directory = dirname($outputPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $json = json_encode(
            $emeaCourses,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            $this->error('Failed to encode EMEA courses as JSON.');

            return self::FAILURE;
        }

        File::put($outputPath, $json);

        $this->info('EMEA courses exported: '.count($emeaCourses));
        $this->info("Written to: {$outputPath}");

        return self::SUCCESS;
    }
}
