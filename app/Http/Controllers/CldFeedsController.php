<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    public function courses(Request $request)
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

            $courses[] = [
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
                // FeedMe expects string values, not booleans
                'isRecommended' => (! empty($row->isRecommended) && (string) $row->isRecommended !== '0') ? 'true' : 'false',
                // matches old behavior: prefix with current language (no delimiter)
                'courseLanguageCategoriesSlug' => $currentLanguage.(string) ($row->courseLanguageCategoriesSlug ?? ''),
                'pricingTier' => (string) ($row->pricingTier ?? ''),
                'courseImageUrl' => (string) ($row->courseImageUrl ?? ''),
                'courseImageThumbUrl' => (string) ($row->courseImageThumbUrl ?? ''),
                'courseInformation' => $this->encodeSpecialCharacters((string) ($row->courseInformation ?? '')),
                'marketingDescription' => $this->encodeSpecialCharacters((string) ($row->marketingDescription ?? '')),
                'courseOutline' => $this->encodeSpecialCharacters((string) ($row->courseOutline ?? '')),
                'courseObjectives' => $this->encodeSpecialCharacters((string) ($row->courseObjectives ?? '')),
                'courseRegulations' => $this->encodeSpecialCharacters((string) ($row->courseRegulations ?? '')),
            ];
        }

        return response()->json(
            $courses,
            200,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Singles feed (from course_api_data_singles).
     * Old equivalent: create-api-json-feed-singles.php
     */
    public function singles(Request $request)
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

            $courses[] = [
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
                'courseOutline' => $this->encodeSpecialCharacters((string) ($row->courseOutline ?? '')),
                'courseObjectives' => $this->encodeSpecialCharacters((string) ($row->courseObjectives ?? '')),
                'courseRegulations' => $this->encodeSpecialCharacters((string) ($row->courseRegulations ?? '')),
            ];
        }

        return response()->json(
            $courses,
            200,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
