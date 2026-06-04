<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessCldSinglesRequest;
use App\Services\Cld\CldSyncNotifier;
use App\Services\CldApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CoursesController extends Controller
{
    /**
     * Display a listing of courses.
     */
    public function index()
    {
        return view('courses.index', [
            'maxSinglesIds' => (int) config('cld_api.ui.max_singles_ids', 50),
            'feedMeSinglesFeedId' => (int) config('cld_api.feedme.singles_feed_id'),
        ]);
    }

    /**
     * Run CLD singles sync from the Courses Manager UI.
     */
    public function processSingles(ProcessCldSinglesRequest $request, CldApiService $cldApi, CldSyncNotifier $syncNotifier)
    {
        set_time_limit(0);

        $ids = $request->parsedLessonIds();

        $runFeedMe = $request->boolean('send_to_craft')
            && ! empty(config('cld_api.feedme.singles_passkey'));
        $feedId = (int) config('cld_api.feedme.singles_feed_id');

        $startedAt = microtime(true);
        $runId = null;
        try {
            $runId = DB::table('cld_sync_runs')->insertGetId([
                'mode' => 'singles',
                'trigger' => 'ui:/courses',
                'requested_ids' => implode(',', $ids),
                'total' => count($ids),
                'succeeded' => 0,
                'failed' => 0,
                'send_to_craft' => $request->boolean('send_to_craft'),
                'feedme_configured' => ! empty(config('cld_api.feedme.singles_passkey')),
                'feedme_ran' => false,
                'feedme_ok' => null,
                'feedme_http_code' => null,
                'abort_reason' => null,
                'duration_ms' => null,
                'started_at' => now(),
                'finished_at' => null,
                'meta' => json_encode([
                    'feed_id' => $feedId,
                    'user_id' => $request->user()?->id,
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
                runFeedMe: $runFeedMe,
                vimeoIdsByLessonId: $request->parsedVimeoIdsByLessonId(),
            );
            $syncNotifier->notify($result);
        } catch (\Throwable $e) {
            report($e);
            $syncNotifier->notifyException($e, 'Courses Manager CLD singles sync');

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

            return redirect()
                ->route('courses.index')
                ->withInput()
                ->with('error', 'Processing failed: '.$e->getMessage());
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
            return redirect()
                ->route('courses.index')
                ->withInput()
                ->with('error', $result->abortReason);
        }

        $userWantedCraft = $request->boolean('send_to_craft');
        $feedMeConfigured = ! empty(config('cld_api.feedme.singles_passkey'));
        $singlesFeedUrl = route('feeds.cld.courses.singles');
        if (! empty(config('cld_api.feeds.passkey'))) {
            $singlesFeedUrl .= '?passkey='.rawurlencode((string) config('cld_api.feeds.passkey'));
        }

        $cldSyncUi = [
            'total' => count($ids),
            'succeeded' => $result->succeeded,
            'failed' => count($result->failures),
            'user_wanted_craft' => $userWantedCraft,
            'feedme_configured' => $feedMeConfigured,
            'craft_ran' => $runFeedMe,
            'feedme_ok' => $result->feedMe['ok'] ?? null,
            'singles_feed_url' => $singlesFeedUrl,
        ];

        return redirect()
            ->route('courses.index')
            ->with('cld_sync_ui', $cldSyncUi);
    }

    /**
     * Display the specified course.
     */
    public function show($id)
    {
        return view('courses.show', compact('id'));
    }

    /**
     * Show the form for editing the specified course.
     */
    public function edit($id)
    {
        return view('courses.edit', compact('id'));
    }

    /**
     * Update the specified course.
     */
    public function update(Request $request, $id)
    {
        // TODO: Implement course update logic
        return redirect()->route('courses.index')->with('success', 'Course updated successfully.');
    }

    /**
     * Remove the specified course.
     */
    public function destroy($id)
    {
        // TODO: Implement course deletion logic
        return redirect()->route('courses.index')->with('success', 'Course deleted successfully.');
    }
}
