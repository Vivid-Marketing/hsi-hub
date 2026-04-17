<?php

namespace App\Console\Commands;

use App\Services\Cld\CldSyncNotifier;
use App\Services\CldApiService;
use Illuminate\Console\Command;

class CldSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cld:sync
                            {--no-feedme : Skip triggering FeedMe after sync}
                            {--feed-id=70 : FeedMe feed ID}
                            {--basic-filter : Only use LastUpdate (skip image/locale checks)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync CLD API course data (cron-style: courses updated in last month, no manual list)';

    /**
     * Execute the console command.
     */
    public function handle(CldApiService $cldApi, CldSyncNotifier $notifier): int
    {
        $runFeedMe = ! $this->option('no-feedme');
        $feedId = (int) $this->option('feed-id');
        $includeImageAndLocaleChecks = ! $this->option('basic-filter');

        $this->info('Starting CLD API sync (runFeedMe='.($runFeedMe ? 'yes' : 'no').')');

        try {
            $result = $cldApi->cronJobGenerateAddUpdateCldApiDataFromList(
                manualList: [],
                feedId: $feedId,
                doSingleBatch: false,
                runFeedMe: $runFeedMe,
                includeImageAndLocaleChecks: $includeImageAndLocaleChecks
            );
            $notifier->notify($result);
        } catch (\Throwable $e) {
            $notifier->notifyException($e, 'cld:sync');
            $this->error('CLD sync failed: '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
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

        $this->info('CLD sync finished.');

        return self::SUCCESS;
    }
}
