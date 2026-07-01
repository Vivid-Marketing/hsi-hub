<?php

namespace App\Console\Commands;

use App\Services\Hsi\HsiAlgoliaEmbedder;
use Illuminate\Console\Command;

class HsiEmbedAlgolia extends Command
{
  protected $signature = 'hsi:embed-algolia
                          {--type= : Only embed a specific type: course or blog_news}';

  protected $description = 'Browse Algolia indexes and embed records into hsi_chunks for semantic search';

  public function handle(HsiAlgoliaEmbedder $embedder): int
  {
    $typeFilter = $this->option('type');

    $indexes = [
      'course' => (string) config('hsi_ai.algolia.indexes.courses', ''),
      'blog_news' => (string) config('hsi_ai.algolia.indexes.blog_news', ''),
    ];

    $total = 0;

    foreach ($indexes as $sourceType => $indexName) {
      if ($typeFilter !== null && $typeFilter !== $sourceType) {
        continue;
      }

      if ($indexName === '') {
        $envKey = $sourceType === 'course' ? 'ALGOLIA_INDEX_COURSES' : 'ALGOLIA_INDEX_BLOG_NEWS';
        $this->warn("Skipping {$sourceType}: {$envKey} not set.");
        continue;
      }

      $this->line("Embedding {$sourceType} from index: {$indexName}");

      try {
        $count = $embedder->embedIndex($indexName, $sourceType);
      } catch (\Throwable $e) {
        $this->error('  Failed: '.$e->getMessage());

        return self::FAILURE;
      }

      $this->info("  Embedded {$count} chunk(s)");
      $total += $count;
    }

    $this->newLine();
    $this->info("Done. Total chunks embedded: {$total}");

    return self::SUCCESS;
  }
}
