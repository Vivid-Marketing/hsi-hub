<?php

namespace App\Console\Commands;

use App\Services\CldApiService;
use Illuminate\Console\Command;

class CldExportLongFieldDescriptionBackfill extends Command
{
    protected $signature = 'cld:export-long-field-description-backfill
                            {--output= : Output JSON path (default: storage/app/cld-api/backfill-long-field-description.json)}
                            {--craft-export= : Craft courses export JSON path}';

    protected $description = 'Export CLD GEODescription backfill JSON for Craft longFieldDescription (scoped to enabled Craft courses)';

    public function handle(CldApiService $cldApi): int
    {
        ini_set('memory_limit', '2G');

        $outputPath = $this->option('output') ?: $cldApi->longFieldDescriptionBackfillExportPath();
        $craftExportPath = $this->option('craft-export') ?: null;

        $this->info('Reading enabled Craft course cldIds and fetching CLD catalog...');

        $count = $cldApi->writeLongFieldDescriptionBackfillExport($outputPath, $craftExportPath);

        if ($count === null) {
            $this->error('Failed to export longFieldDescription backfill.');

            return self::FAILURE;
        }

        $this->info("Backfill rows exported: {$count}");
        $this->info("Written to: {$outputPath}");

        return self::SUCCESS;
    }
}
