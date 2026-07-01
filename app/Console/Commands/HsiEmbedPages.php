<?php

namespace App\Console\Commands;

use App\Models\HsiPage;
use App\Services\Hsi\HsiChunkEmbedder;
use Illuminate\Console\Command;

class HsiEmbedPages extends Command
{
  protected $signature = 'hsi:embed-pages
                          {--max=0 : Max pages to embed (0 = all)}
                          {--force : Re-embed even if chunks already exist}
                          {--page-id= : Embed a single page by ID}';

  protected $description = 'Chunk crawled HSI pages and embed them with Ollama for semantic search';

  public function handle(HsiChunkEmbedder $embedder): int
  {
    $query = HsiPage::query()
      ->where(function ($builder) {
        $builder->whereNull('crawl_status')
          ->orWhere('crawl_status', '!=', 'error');
      })
      ->whereNotNull('body_text')
      ->orderBy('id');

    if ($this->option('page-id')) {
      $query->where('id', (int) $this->option('page-id'));
    }

    $max = (int) $this->option('max');
    if ($max > 0) {
      $query->limit($max);
    }

    $pages = $query->get();
    if ($pages->isEmpty()) {
      $this->warn('No pages found to embed.');
      return self::SUCCESS;
    }

    $force = (bool) $this->option('force');
    $embeddedChunks = 0;
    $skipped = 0;

    foreach ($pages as $page) {
      $this->line("Page #{$page->id}: ".($page->title ?: $page->dedupe_key));

      try {
        $count = $embedder->embedPage($page, $force);
      } catch (\Throwable $e) {
        $this->error('  Failed: '.$e->getMessage());
        return self::FAILURE;
      }

      if ($count === 0) {
        $skipped++;
        $this->warn('  Skipped (unchanged or empty)');
        continue;
      }

      $embeddedChunks += $count;
      $this->info("  Embedded {$count} chunk(s)");
    }

    $this->newLine();
    $this->info("Done. Chunks embedded: {$embeddedChunks}, pages skipped: {$skipped}");

    return self::SUCCESS;
  }
}
