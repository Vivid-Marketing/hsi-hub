<?php

namespace App\Http\Controllers;

use App\Services\CldApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CldFeedsController extends Controller
{
    private function guardPasskey(Request $request): void
    {
        $passkey = config('cld_api.feeds.passkey');
        if (empty($passkey)) {
            return;
        }

        if ($request->query('passkey') !== $passkey) {
            abort(403);
        }
    }

    private function encodeSpecialCharacters(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }

    private function languageSlugFromLocale(?string $locale): string
    {
        return match ($locale) {
            'fr_CA' => 'french-canadian',
            'es_US' => 'spanish',
            default => 'english',
        };
    }

    private function affiliationsToPipe(?string $serializedAffiliations): string
    {
        if (empty($serializedAffiliations)) {
            return '';
        }

        $affsArr = @unserialize($serializedAffiliations);
        if (! is_array($affsArr)) {
            return '';
        }

        $affs = [];
        foreach ($affsArr as $aff) {
            if (is_array($aff) && isset($aff['Description'])) {
                $affs[] = $aff['Description'];
            }
        }

        return implode('|', $affs);
    }

    /**
     * Full feed (from course_api_data).
     * Old equivalent: create-api-xml-feed.php (despite the filename, it outputs JSON).
     */
    public function courses(Request $request, CldApiService $cldApi)
    {
        $this->guardPasskey($request);

        $rows = DB::table('course_api_data')->get();
        $courses = [];

        foreach ($rows as $row) {
            if (! empty($row->parentCldid)) {
                continue;
            }

            $currentLanguage = $this->languageSlugFromLocale($row->locale ?? null);
            $affsString = $this->affiliationsToPipe($row->lessonAffiliations ?? null);

            $courses[] = $this->courseRowToFeedJson($row, $currentLanguage, $affsString, $cldApi);
        }

        return response()->json(
            $courses,
            200,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * EMEA Web Catalog feed (temp public endpoint, no passkey).
     * Serves cached JSON from storage; use ?refresh=1 to rebuild from CLD API.
     */
    public function emea(Request $request, CldApiService $cldApi)
    {
        $path = $cldApi->emeaCoursesExportPath();

        if ($request->boolean('refresh') || ! File::exists($path)) {
            ini_set('memory_limit', '2G');

            $count = $cldApi->writeEmeaCoursesExport($path);
            if ($count === null) {
                abort(503, 'Failed to fetch EMEA courses from CLD API.');
            }
        }

        return response(
            File::get($path),
            200,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Backfill feed for Craft longFieldDescription (from CLD GEODescription).
     * Scoped to enabled Craft course entries with a cldId.
     * Serves cached JSON from storage; use ?refresh=1 to rebuild from CLD API.
     */
    public function backfillLongFieldDescription(Request $request, CldApiService $cldApi)
    {
        $path = $cldApi->longFieldDescriptionBackfillExportPath();

        if ($request->boolean('refresh') || ! File::exists($path)) {
            ini_set('memory_limit', '2G');

            $count = $cldApi->writeLongFieldDescriptionBackfillExport($path);
            if ($count === null) {
                abort(503, 'Failed to build longFieldDescription backfill from Craft export + CLD API.');
            }
        }

        return response(
            File::get($path),
            200,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Backfill feed for Craft courseOutline (pipe-delimited LessonSection names).
     * Scoped to enabled Craft course entries with a cldId.
     * Serves cached JSON from storage; use ?refresh=1 to rebuild from CLD API.
     */
    public function backfillCourseOutline(Request $request, CldApiService $cldApi)
    {
        $path = $cldApi->courseOutlineBackfillExportPath();

        if ($request->boolean('refresh') || ! File::exists($path)) {
            ini_set('memory_limit', '2G');

            $count = $cldApi->writeCourseOutlineBackfillExport($path);
            if ($count === null) {
                abort(503, 'Failed to build courseOutline backfill from Craft export + CLD API.');
            }
        }

        return response(
            File::get($path),
            200,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Targeted courseOutlineList backfill (non-empty pipe lists only).
     * Serves storage/app/cld-api/backfill-course-outline-empty-retry.json
     */
    public function backfillCourseOutlineList(CldApiService $cldApi)
    {
        $path = $cldApi->courseOutlineListBackfillExportPath();

        if (! File::exists($path)) {
            abort(404, 'courseOutlineList backfill JSON not found. Generate it locally and deploy the file to storage/app/cld-api/.');
        }

        return response(
            File::get($path),
            200,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Singles feed (from course_api_data_singles).
     * Old equivalent: create-api-json-feed-singles.php
     */
    public function singles(Request $request, CldApiService $cldApi)
    {
        $this->guardPasskey($request);

        $rows = DB::table('course_api_data_singles')->get();
        $courses = [];

        foreach ($rows as $row) {
            if (! empty($row->parentCldid)) {
                continue;
            }

            $currentLanguage = $this->languageSlugFromLocale($row->locale ?? null);
            $affsString = $this->affiliationsToPipe($row->lessonAffiliations ?? null);

            $courses[] = $this->courseRowToFeedJson($row, $currentLanguage, $affsString, $cldApi, includeVimeoId: true);
        }

        return response()->json(
            $courses,
            200,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * @return array<string, string>
     */
    private function courseRowToFeedJson(
        object $row,
        string $currentLanguage,
        string $affsString,
        CldApiService $cldApi,
        bool $includeVimeoId = false
    ): array {
        $outlineFields = $cldApi->normalizeCourseOutlineFeedFields(
            isset($row->courseOutline) ? (string) $row->courseOutline : null,
            isset($row->courseOutlineList) ? (string) $row->courseOutlineList : null
        );

        $course = [
            'title' => (string) ($row->title ?? ''),
            'cldId' => (string) ($row->cldId ?? ''),
            'salesLibraryTopic' => $this->encodeSpecialCharacters((string) ($row->salesLibraryTopic ?? '')),
            'courseTopic' => $this->encodeSpecialCharacters((string) ($row->courseTopic ?? '')),
            'collections' => (string) ($row->collections ?? ''),
            'vendorId' => (string) ($row->vendorId ?? ''),
            'vendorName' => (string) ($row->vendorName ?? ''),
            'libraryId' => (string) ($row->libraryId ?? ''),
            'libraryName' => $this->encodeSpecialCharacters((string) ($row->libraryName ?? '')),
            'lessonId' => (string) ($row->lessonId ?? ''),
            'ej4CourseNumber' => (string) ($row->ej4CourseNumber ?? ''),
            'lessonModality' => (string) ($row->lessonModality ?? ''),
            'hsiProgramID' => (string) ($row->hsiProgramID ?? ''),
            'lessonLength' => (string) ($row->lessonLength ?? ''),
            'locale' => (string) ($row->locale ?? ''),
            'allLocales' => (string) ($row->allLocales ?? ''),
            'lessonAffiliations' => $affsString,
            'isRecommended' => (! empty($row->isRecommended) && (string) $row->isRecommended !== '0') ? 'true' : 'false',
            'courseLanguageCategoriesSlug' => $currentLanguage.(string) ($row->courseLanguageCategoriesSlug ?? ''),
            'pricingTier' => (string) ($row->pricingTier ?? ''),
            'courseImageUrl' => (string) ($row->courseImageUrl ?? ''),
            'courseImageThumbUrl' => (string) ($row->courseImageThumbUrl ?? ''),
            'courseInformation' => $this->encodeSpecialCharacters((string) ($row->courseInformation ?? '')),
            'marketingDescription' => $this->encodeSpecialCharacters((string) ($row->marketingDescription ?? '')),
            'courseOutline' => $this->encodeSpecialCharacters($outlineFields['courseOutline']),
            'courseOutlineList' => $this->encodeSpecialCharacters($outlineFields['courseOutlineList']),
            'courseObjectives' => $this->encodeSpecialCharacters((string) ($row->courseObjectives ?? '')),
            'courseRegulations' => $this->encodeSpecialCharacters((string) ($row->courseRegulations ?? '')),
        ];

        if ($includeVimeoId) {
            $course['vimeoId'] = (string) ($row->vimeoId ?? '');
        }

        return $course;
    }
}
