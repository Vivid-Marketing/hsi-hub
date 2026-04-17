<?php

namespace App\Console\Commands;

use App\Services\Cld\CldSyncNotifier;
use App\Services\CldApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CldSyncSingles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cld:sync-singles
                            {ids* : One or more CLD Lesson IDs to fetch (e.g. 7142 15394)}
                            {--no-feedme : Skip triggering FeedMe after sync}
                            {--feed-id=70 : FeedMe feed ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync specific CLD course IDs into course_api_data_singles (truncates table first)';

    /**
     * Execute the console command.
     */
    public function handle(CldApiService $cldApi, CldSyncNotifier $notifier): int
    {
        $ids = $this->argument('ids');
        $ids = array_values(array_filter(array_map(static fn ($v) => is_numeric($v) ? (int) $v : null, $ids)));

        if (empty($ids)) {
            $this->error('No valid numeric IDs provided.');

            return self::FAILURE;
        }

        $runFeedMe = ! $this->option('no-feedme');
        $feedId = (int) $this->option('feed-id');

        $this->info('Starting CLD API singles sync (ids='.implode(',', $ids).', runFeedMe='.($runFeedMe ? 'yes' : 'no').')');

        $startedAt = microtime(true);
        $runId = null;
        try {
            $runId = DB::table('cld_sync_runs')->insertGetId([
                'mode' => 'singles',
                'trigger' => 'cli:cld:sync-singles',
                'requested_ids' => implode(',', $ids),
                'total' => count($ids),
                'succeeded' => 0,
                'failed' => 0,
                'send_to_craft' => $runFeedMe,
                'feedme_configured' => ! empty(config('cld_api.feedme.passkey')),
                'feedme_ran' => false,
                'feedme_ok' => null,
                'feedme_http_code' => null,
                'abort_reason' => null,
                'duration_ms' => null,
                'started_at' => now(),
                'finished_at' => null,
                'meta' => json_encode([
                    'feed_id' => $feedId,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not create cld_sync_runs row', ['error' => $e->getMessage()]);
        }

        try {
            $result = $cldApi->cronJobGenerateAddUpdateCldApiDataFromList(
                manualList: $ids,
                feedId: $feedId,
                doSingleBatch: true,
                runFeedMe: $runFeedMe
            );
            $notifier->notify($result);
        } catch (\Throwable $e) {
            $notifier->notifyException($e, 'cld:sync-singles');
            $this->error('CLD singles sync failed: '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            if ($runId !== null) {
                try {
                    DB::table('cld_sync_runs')->where('id', $runId)->update([
                        'abort_reason' => $e->getMessage(),
                        'finished_at' => now(),
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $updateErr) {
                    Log::warning('Could not update cld_sync_runs row after exception', ['id' => $runId, 'error' => $updateErr->getMessage()]);
                }
            }

            return self::FAILURE;
        }

        if ($runId !== null) {
            try {
                DB::table('cld_sync_runs')->where('id', $runId)->update([
                    'total' => $result->totalIds,
                    'succeeded' => $result->succeeded,
                    'failed' => count($result->failures),
                    'feedme_ran' => (bool) ($result->feedMe !== null),
                    'feedme_ok' => $result->feedMe['ok'] ?? null,
                    'feedme_http_code' => $result->feedMe['http_code'] ?? null,
                    'abort_reason' => $result->abortReason,
                    'finished_at' => now(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $updateErr) {
                Log::warning('Could not update cld_sync_runs row after success', ['id' => $runId, 'error' => $updateErr->getMessage()]);
            }
        }

        if (! empty($result->abortReason)) {
            $this->warn($result->abortReason);
        }
        if ($result->hasIssues()) {
            $this->warn('Completed with issues — check notification email or logs.');
            foreach ($result->failures as $row) {
                $this->line("  Lesson {$row['lesson_id']}: {$row['reason']}");
            }
            if ($result->feedMe !== null && empty($result->feedMe['ok'])) {
                $this->warn('FeedMe request did not succeed (HTTP '.($result->feedMe['http_code'] ?? '?').').');
            }
        }

        $this->info('CLD singles sync finished.');

        return self::SUCCESS;
    }
}
