<?php

namespace App\Console\Commands;

use App\Models\HsiPage;
use App\Services\Hsi\HsiPageClassifier;
use App\Services\Hsi\HsiPageCrawler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class HsiCrawlPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hsi:crawl-pages
                            {--max=0 : Max URLs to crawl (0 = all)}
                            {--url=* : Crawl only these URLs (repeatable; overrides config seeds)}
                            {--dry-run : Fetch/parse but do not write to DB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl primary HSI pages (seeded), normalize, dedupe, and cache structured page data';

    /**
     * Execute the console command.
     */
    public function handle(HsiPageCrawler $crawler, HsiPageClassifier $classifier): int
    {
        $seedUrls = (array) config('hsi_crawl.seed_urls', []);
        $override = (array) $this->option('url');
        $urls = ! empty($override) ? $override : $seedUrls;

        $urls = array_values(array_unique(array_filter(array_map('trim', $urls))));
        $max = (int) $this->option('max');
        $dryRun = (bool) $this->option('dry-run');

        if ($max > 0) {
            $urls = array_slice($urls, 0, $max);
        }

        if (empty($urls)) {
            $this->warn('No URLs provided (config seeds empty and no --url overrides).');
            return self::SUCCESS;
        }

        $this->info('Crawling '.count($urls).' URL(s)'.($dryRun ? ' (dry-run)' : '').'...');

        $seenDedupeKeys = [];
        $ok = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($urls as $i => $url) {
            $this->line('['.($i + 1).'/'.count($urls).'] '.$url);

            $data = $crawler->crawl($url);
            $dedupeKey = $data['dedupe_key'];

            if (isset($seenDedupeKeys[$dedupeKey])) {
                $skipped++;
                $this->warn('  Skipping duplicate (dedupe_key): '.$dedupeKey);
                continue;
            }
            $seenDedupeKeys[$dedupeKey] = true;

            if (! $data['ok']) {
                $failed++;
                $this->warn('  Failed: '.($data['error'] ?? 'unknown error'));
            } else {
                $ok++;
                $this->info('  OK: '.($data['canonical_url'] ?? $data['fetched_url'] ?? $dedupeKey));
            }

            if ($dryRun) {
                continue;
            }

            $existing = HsiPage::where('dedupe_key', $dedupeKey)->first();
            $newHash = $data['content_hash'] ?? null;
            $changed = $existing === null || ($newHash !== null && $existing->content_hash !== $newHash);

            $pageUrl = $data['canonical_url'] ?: ($data['fetched_url'] ?: $url);

            $update = [
                'seed_url' => $data['seed_url'],
                'fetched_url' => $data['fetched_url'],
                'canonical_url' => $data['canonical_url'],
                'source_group' => $classifier->sourceGroupForSeedPath($url),
                'page_type' => $classifier->pageTypeForUrl($pageUrl),
                'title' => $data['title'],
                'meta_description' => $data['meta_description'],
                'h1s' => $data['h1s'],
                'h2s' => $data['h2s'],
                'content_hash' => $newHash,
                'http_status' => $data['http_status'],
                'content_type' => $data['content_type'],
                'crawl_status' => $data['ok'] ? ($changed ? 'changed' : 'unchanged') : 'error',
                'last_error' => $data['ok'] ? null : ($data['error'] ?? 'unknown error'),
                // Keep legacy field in sync (can be dropped later).
                'error' => $data['ok'] ? null : ($data['error'] ?? 'unknown error'),
                'last_crawled_at' => Carbon::now(),
            ];

            if ($changed) {
                $update['body_text'] = $data['body_text'];
                $update['raw_html'] = $data['raw_html'];
            }

            HsiPage::updateOrCreate(['dedupe_key' => $dedupeKey], $update);
        }

        $this->newLine();
        $this->info('Done.');
        $this->line("OK: {$ok}  Skipped: {$skipped}  Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

