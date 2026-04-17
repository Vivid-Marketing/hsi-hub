<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessCldSinglesRequest;
use App\Services\Cld\CldSyncNotifier;
use App\Services\CldApiService;
use Illuminate\Http\Request;

class CoursesController extends Controller
{
    /**
     * Display a listing of courses.
     */
    public function index()
    {
        return view('courses.index', [
            'maxSinglesIds' => (int) config('cld_api.ui.max_singles_ids', 50),
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
            && ! empty(config('cld_api.feedme.passkey'));
        $feedId = (int) config('cld_api.feedme.prod_feed_id', 70);

        try {
            $result = $cldApi->cronJobGenerateAddUpdateCldApiDataFromList(
                manualList: $ids,
                feedId: $feedId,
                doSingleBatch: true,
                runFeedMe: $runFeedMe
            );
            $syncNotifier->notify($result);
        } catch (\Throwable $e) {
            report($e);
            $syncNotifier->notifyException($e, 'Courses Manager CLD singles sync');

            return redirect()
                ->route('courses.index')
                ->withInput()
                ->with('error', 'Processing failed: '.$e->getMessage());
        }

        if (! empty($result->abortReason)) {
            return redirect()
                ->route('courses.index')
                ->withInput()
                ->with('error', $result->abortReason);
        }

        $userWantedCraft = $request->boolean('send_to_craft');
        $feedMeConfigured = ! empty(config('cld_api.feedme.passkey'));
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
