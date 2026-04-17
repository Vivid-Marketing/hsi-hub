<?php

namespace App\Services;

use App\Services\Cld\CldSyncResult;
use Aws\S3\S3Client;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CldApiService
{
    protected string $baseUrl;

    protected int $tokenTtl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('cld_api.base_url', 'https://cldapi.hsiplatform.com'), '/');
        $this->tokenTtl = config('cld_api.token_ttl_seconds', 3601 * 4);
    }

    private function downloadBinary(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'image/*,*/*;q=0.8',
                'User-Agent' => 'hub-hsi/1.0 (cld-course-image-fetch)',
            ])->timeout(60)->connectTimeout(15)->get($url);

            return [
                'ok' => $response->successful(),
                'data' => $response->body(),
                'httpCode' => $response->status(),
                'contentType' => (string) ($response->header('Content-Type') ?? ''),
                'curlErrNo' => 0,
                'curlErr' => '',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'data' => null,
                'httpCode' => 0,
                'contentType' => '',
                'curlErrNo' => 1,
                'curlErr' => $e->getMessage(),
            ];
        }
    }

    private function describePath(string $path): array
    {
        $exists = file_exists($path);
        $isDir = is_dir($path);
        $isWritable = is_writable($path);
        $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : '----';
        $owner = $exists ? @fileowner($path) : null;
        $group = $exists ? @filegroup($path) : null;

        return [
            'path' => $path,
            'exists' => $exists,
            'isDir' => $isDir,
            'isWritable' => $isWritable,
            'perms' => $perms,
            'owner' => $owner,
            'group' => $group,
        ];
    }

    /**
     * Get Bearer token from DB or refresh from CLD API.
     */
    public function getBearerToken(): string
    {
        // Be tolerant of empty table or multiple rows (legacy/accidental inserts).
        // We always use the newest token row.
        $row = DB::table('cld_api_tokens')->orderByDesc('cldatkid')->first();
        $oldToken = $row->token ?? null;
        $lastTokenDateTime = $row->datetime_created ?? null;

        if (! empty($oldToken) && ! empty($lastTokenDateTime) && time() - strtotime($lastTokenDateTime) < $this->tokenTtl) {
            return $oldToken;
        }

        $adminId = config('cld_api.admin_id');
        $password = config('cld_api.password');
        if (empty($adminId) || empty($password)) {
            throw new \RuntimeException('CLD API credentials not set. Configure CLD_API_ADMIN_ID and CLD_API_PASSWORD in .env');
        }

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->connectTimeout(15)
            ->timeout(60)
            ->retry(2, 500)
            ->post($this->baseUrl.'/api/auth/login', [
                'Admin_ID' => $adminId,
                'Password' => $password,
            ]);

        $data = $response->json();
        if (empty($data['Token'])) {
            Log::error('CLD API auth failed', ['response' => $response->body()]);
            throw new \RuntimeException('CLD API: no token in response');
        }

        $payload = [
            'token' => $data['Token'],
            'datetime_created' => date('Y-m-d H:i:s'),
        ];

        if (! empty($row?->cldatkid)) {
            DB::table('cld_api_tokens')->where('cldatkid', $row->cldatkid)->update($payload);
        } else {
            DB::table('cld_api_tokens')->insert($payload);
        }

        return $data['Token'];
    }

    protected function doCurlGetRequest(string $requestUrl, string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->connectTimeout(15)
                ->timeout(60)
                ->retry(2, 500)
                ->get($requestUrl);
        } catch (\Throwable $e) {
            Log::error('CLD API request failed', ['url' => $requestUrl, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function getLastUpdatedList(string $token): ?array
    {
        return $this->doCurlGetRequest($this->baseUrl.'/api/marketing/courses', $token);
    }

    public function getMarketingCoursesInactive(string $token): ?array
    {
        return $this->doCurlGetRequest($this->baseUrl.'/api/marketing/courses/inactive', $token);
    }

    public function getCourseTopic($catalogTopicId, string $token): ?array
    {
        return $this->doCurlGetRequest($this->baseUrl.'/api/CatalogTopic/'.$catalogTopicId, $token);
    }

    public function getSalesLibraryTopic($catalogLibraryId, string $token): ?array
    {
        return $this->doCurlGetRequest($this->baseUrl.'/api/CatalogLibrary/'.$catalogLibraryId, $token);
    }

    public function getCldDataRequest(string $token, $cldid): ?array
    {
        return $this->doCurlGetRequest($this->baseUrl.'/api/catalog/'.$cldid, $token);
    }

    public function getCourseOutline($cldid, string $token): ?array
    {
        return $this->doCurlGetRequest($this->baseUrl.'/api/LessonSection?text='.$cldid, $token);
    }

    public function getCourseObjectives($cldid, string $token): ?array
    {
        return $this->doCurlGetRequest($this->baseUrl.'/api/lessonobjective/primary/'.$cldid, $token);
    }

    public function getCourseRegulations($cldid, string $token): ?array
    {
        return $this->doCurlGetRequest($this->baseUrl.'/api/RegulatoryRequirement?text='.$cldid, $token);
    }

    public function getCldThumbnailUrl($lessonId, string $token): string|false
    {
        $thumbnailData = $this->doCurlGetRequest($this->baseUrl.'/api/LessonThumbnail/'.$lessonId, $token);
        if (! is_array($thumbnailData) || empty($thumbnailData)) {
            return false;
        }
        $thumbnailId = '';
        $dimensions = '1920x1080';
        foreach ($thumbnailData as $data) {
            if (isset($data['IsPrimary']) && ($data['IsPrimary'] == 1 || $data['IsPrimary'] === true)) {
                $thumbnailId = $data['ThumbnailID'] ?? '';
                $dimensions = $data['ImageDimensions'] ?? '1920x1080';
                break;
            }
            $thumbnailId = $data['ThumbnailID'] ?? '';
            $dimensions = $data['ImageDimensions'] ?? '1920x1080';
        }
        if (! empty($thumbnailId)) {
            return $this->baseUrl.'/api/preview/thumbnails/'.$thumbnailId.'?dimensions='.$dimensions;
        }

        return false;
    }

    public function getFirstLocaleFromLocales(array $localesArray): string
    {
        foreach ($localesArray as $locale) {
            return $locale['LocaleCode'] ?? '';
        }

        return '';
    }

    public function convertSpecialChar(string $text): string
    {
        return preg_replace('/&([a-z])[a-z]+;/i', '$1', htmlentities($text));
    }

    public static function slugify(string $text, string $divider = '-'): string
    {
        $text = preg_replace('~[^\pL\d]+~u', $divider, $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, $divider);
        $text = preg_replace('~-+~', $divider, $text);
        $text = strtolower($text);

        return empty($text) ? 'n-a' : $text;
    }

    protected function courseImagesPath(string $subPath = ''): string
    {
        $base = storage_path('app/'.config('cld_api.storage.course_images', 'cld-api/course-images'));

        return $subPath ? $base.'/'.ltrim($subPath, '/') : $base;
    }

    protected function ensureDirectoryExists(string $dirPath, int $permissions = 0755): bool
    {
        if (empty($dirPath)) {
            return false;
        }
        $dirPath = rtrim($dirPath, '/');
        if (is_dir($dirPath)) {
            if (is_writable($dirPath)) {
                return true;
            }
            @chmod($dirPath, $permissions);

            return is_writable($dirPath);
        }
        if (! @mkdir($dirPath, $permissions, true)) {
            Log::error("Failed to create directory: {$dirPath}");

            return false;
        }

        return is_dir($dirPath) && is_writable($dirPath);
    }

    protected function generateImageThumbnail(string $sourcePath, string $thumbnailPath, int $width, int $height): bool
    {
        if (! @getimagesize($sourcePath)) {
            return false;
        }
        $outputDir = dirname($thumbnailPath);
        if (! $this->ensureDirectoryExists($outputDir)) {
            return false;
        }
        [$source_image_width, $source_image_height, $source_image_type] = getimagesize($sourcePath);
        switch ($source_image_type) {
            case IMAGETYPE_GIF:
                $source_gd = @imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_JPEG:
                $source_gd = @imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source_gd = @imagecreatefrompng($sourcePath);
                break;
            default:
                return false;
        }
        if ($source_gd === false) {
            return false;
        }
        $source_aspect = $source_image_width / $source_image_height;
        $thumb_aspect = $width / $height;
        if ($source_image_width <= $width && $source_image_height <= $height) {
            $tw = $source_image_width;
            $th = $source_image_height;
        } elseif ($thumb_aspect > $source_aspect) {
            $tw = (int) ($height * $source_aspect);
            $th = $height;
        } else {
            $tw = $width;
            $th = (int) ($width / $source_aspect);
        }
        $thumb_gd = imagecreatetruecolor($tw, $th);
        imagecopyresampled($thumb_gd, $source_gd, 0, 0, 0, 0, $tw, $th, $source_image_width, $source_image_height);
        $ok = @imagejpeg($thumb_gd, $thumbnailPath, 90);
        imagedestroy($source_gd);
        imagedestroy($thumb_gd);

        return (bool) $ok;
    }

    protected function uploadToDoSpaces(string $pathToFile, string $filename, string $type = 'image/jpeg'): ?string
    {
        $key = config('cld_api.do_spaces.key');
        $secret = config('cld_api.do_spaces.secret');
        $bucket = config('cld_api.do_spaces.bucket');
        $region = config('cld_api.do_spaces.region');
        $endpoint = config('cld_api.do_spaces.endpoint');
        if (empty($key) || empty($secret)) {
            Log::warning('CLD DO Spaces credentials not set. Skipping CDN upload.');

            return null;
        }
        $client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => false,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);
        $result = $client->putObject([
            'Bucket' => $bucket,
            'Key' => $filename,
            'SourceFile' => $pathToFile,
            'ACL' => 'public-read',
            'ContentType' => $type,
        ]);
        $res = $result->toArray();

        return $res['ObjectURL'] ?? null;
    }

    public function downloadRemoteSrcToLocal(string $remoteSrc, string $cldid, string $lessonName): string|false
    {
        $newfileName = $cldid.'-'.self::slugify($lessonName).'.jpeg';
        $targetDir = $this->courseImagesPath();
        if (! $this->ensureDirectoryExists($targetDir)) {
            $desc = $this->describePath($targetDir);
            Log::error('CLD image dir missing/unusable', ['cldid' => $cldid, 'dir' => $targetDir, 'details' => $desc]);

            return false;
        }
        if (! is_writable($targetDir)) {
            $desc = $this->describePath($targetDir);
            Log::error('CLD image dir not writable', ['cldid' => $cldid, 'dir' => $targetDir, 'details' => $desc]);

            return false;
        }
        $img = $targetDir.'/'.$newfileName;

        $res = $this->downloadBinary($remoteSrc);
        if (! $res['ok']) {
            Log::error('CLD image download failed', [
                'cldid' => $cldid,
                'url' => $remoteSrc,
                'httpCode' => $res['httpCode'],
                'contentType' => $res['contentType'],
                'errNo' => $res['curlErrNo'],
                'err' => $res['curlErr'],
            ]);

            return false;
        }

        $bytes = is_string($res['data']) ? strlen($res['data']) : 0;
        if ($bytes < 256) {
            Log::warning('CLD image payload suspiciously small', [
                'cldid' => $cldid,
                'url' => $remoteSrc,
                'bytes' => $bytes,
                'httpCode' => $res['httpCode'],
                'contentType' => $res['contentType'],
            ]);
        }

        // Write atomically to avoid partial/locked files on shared storage.
        if (file_exists($img) && ! is_writable($img)) {
            @chmod($img, 0664);
            if (! is_writable($img)) {
                @unlink($img);
            }
        }

        $tmp = $img.'.tmp-'.getmypid().'-'.bin2hex(random_bytes(4));
        $tmpWriteOk = (@file_put_contents($tmp, $res['data']) !== false);
        if ($tmpWriteOk) {
            @chmod($tmp, 0664);
            $renameOk = @rename($tmp, $img);
            if (! $renameOk) {
                // Fallback: direct write if rename fails on mounted storage
                @unlink($tmp);
                $tmpWriteOk = (@file_put_contents($img, $res['data']) !== false);
            } else {
                $tmpWriteOk = true;
            }
        }

        if (! $tmpWriteOk) {
            $lastErr = error_get_last();
            $dirDesc = $this->describePath($targetDir);
            $fileDesc = $this->describePath($img);
            Log::error('CLD image write failed', [
                'cldid' => $cldid,
                'img' => $img,
                'lastError' => $lastErr,
                'dir' => $dirDesc,
                'file' => $fileDesc,
            ]);

            return false;
        }

        return $img;
    }

    public function optimizeLargeImage(string $hqPathAndFilename, string $cldid): string|false
    {
        $filename = 'lg-'.basename($hqPathAndFilename);
        $pathToOutput = $this->courseImagesPath('optimized/'.$filename);
        if (empty($hqPathAndFilename)) {
            return false;
        }

        return $this->generateImageThumbnail($hqPathAndFilename, $pathToOutput, 976, 549) ? $pathToOutput : false;
    }

    public function optimizeSmallImage(string $lgPathAndFilename, string $cldid): string|false
    {
        $filename = str_replace('lg-C', 'sm-C', basename($lgPathAndFilename));
        $pathToOutput = $this->courseImagesPath('optimized/small/'.$filename);
        if (empty($lgPathAndFilename)) {
            return false;
        }

        return $this->generateImageThumbnail($lgPathAndFilename, $pathToOutput, 226, 127) ? $pathToOutput : false;
    }

    public function processCldImageUrlToCdn(string $cldImageUrl, string $cldid, string $lessonName): array
    {
        $imageData = [];
        $localHq = $this->downloadRemoteSrcToLocal($cldImageUrl, $cldid, $lessonName);
        if (! $localHq) {
            return $imageData;
        }
        $localLg = $this->optimizeLargeImage($localHq, $cldid);
        if (! $localLg) {
            return $imageData;
        }
        $localSm = $this->optimizeSmallImage($localLg, $cldid);
        if (! $localSm) {
            return $imageData;
        }
        $lgCdnUrl = $this->uploadToDoSpaces($localLg, basename($localLg));
        $smCdnUrl = $this->uploadToDoSpaces($localSm, basename($localSm));
        if (! $lgCdnUrl || ! $smCdnUrl) {
            return $imageData;
        }

        return [
            'localHq' => $localHq,
            'localLg' => $localLg,
            'localSm' => $localSm,
            'lgCdnUrl' => $lgCdnUrl,
            'smCdnUrl' => $smCdnUrl,
        ];
    }

    public function getCourseCldData($cldid): ?array
    {
        $token = $this->getBearerToken();
        $clddata = $this->getCldDataRequest($token, $cldid);
        if (empty($clddata)) {
            return null;
        }
        $item = isset($clddata['LessonID']) ? $clddata : (isset($clddata[0]) ? $clddata[0] : null);
        if (empty($item) || ! isset($item['LessonID'])) {
            return null;
        }

        $CLDID = 'CLD-'.$item['LessonID'];
        $LessonID = $item['LessonID'];
        $LibraryID = $item['LibraryID'];
        $LessonName = $item['LessonName'];
        $VendorName = $item['VendorName'];
        $VendorID = $item['VendorID'];
        $LessonModality = $item['LessonModality'];
        $HSIProgramID = $item['HSIProgramID'];
        $LessonLength = $item['LessonLength'];
        $LessonAffiliations = serialize($item['LessonAffiliations'] ?? []);
        $isRecommended = $item['isRecommended'] ?? false;
        $Ej4CourseNumber = $item['Ej4CourseNumber'] ?? '';
        $PricingTier = $item['PricingTier'] ?? '';
        $LibraryName = $item['LibraryName'] ?? '';
        $mainLocale = $this->getFirstLocaleFromLocales($item['Locales'] ?? []);
        $allLocales = serialize($item['Locales'] ?? []);
        $courseInformation = $item['LessonDescription'] ?? '';
        $CatalogTopic = $this->getCourseTopic($item['CatalogTopicId'] ?? 0, $token);
        $CatalogLibrary = $this->getSalesLibraryTopic($item['CatalogLibraryId'] ?? 0, $token);
        $cldApiImageUrl = $this->getCldThumbnailUrl($item['LessonID'], $token);
        $cdnThumbnailsArray = ['courseImageUrl' => '', 'courseImageThumbUrl' => ''];
        if ($cldApiImageUrl) {
            $cdnImages = $this->processCldImageUrlToCdn($cldApiImageUrl, $CLDID, $LessonName);
            if (! empty($cdnImages['lgCdnUrl']) && ! empty($cdnImages['smCdnUrl'])) {
                $cdnThumbnailsArray = [
                    'courseImageUrl' => $cdnImages['lgCdnUrl'],
                    'courseImageThumbUrl' => $cdnImages['smCdnUrl'],
                ];
            }
        }

        $CourseOutline = $this->getCourseOutline($cldid, $token);
        $courseOutlineHtml = '';
        if (is_array($CourseOutline) && count($CourseOutline) > 0) {
            $courseOutlineHtml = '<ul>';
            foreach ($CourseOutline as $outline) {
                if (! empty($outline['SectionName']) && strlen($outline['SectionName']) == mb_strlen($outline['SectionName'], 'utf-8')) {
                    if (($outline['LocaleID'] ?? 0) == 1) {
                        $courseOutlineHtml .= '<li>'.$this->convertSpecialChar($outline['SectionName']).'</li>';
                    }
                }
            }
            $courseOutlineHtml .= '</ul>';
            if ($courseOutlineHtml === '<ul></ul>') {
                $courseOutlineHtml = '';
            }
        }

        $CourseObjectives = $this->getCourseObjectives($cldid, $token);
        $courseObjectivesHtml = '';
        if (is_array($CourseObjectives) && count($CourseObjectives) > 0) {
            $courseObjectivesHtml = '<ul>';
            foreach ($CourseObjectives as $objective) {
                $courseObjectivesHtml .= '<li>'.$this->convertSpecialChar($objective['ObjectiveText'] ?? '').'</li>';
            }
            $courseObjectivesHtml .= '</ul>';
        }

        $CourseRegulations = $this->getCourseRegulations($cldid, $token);
        $courseRegulationsHtml = '';
        if (is_array($CourseRegulations) && count($CourseRegulations) > 0) {
            $courseRegulationsHtml = '<ul>';
            foreach ($CourseRegulations as $regulation) {
                $courseRegulationsHtml .= '<li>'.$this->convertSpecialChar($regulation['Requirement'] ?? '').'</li>';
            }
            $courseRegulationsHtml .= '</ul>';
        }

        $courseTopic = isset($CatalogTopic[0]) ? $CatalogTopic[0]['CatalogTopicName'] : '';
        if ($courseTopic === 'Psycology') {
            $courseTopic = 'Psychology';
        }

        return [
            'title' => $LessonName,
            'cldId' => $CLDID,
            'salesLibraryTopic' => isset($CatalogLibrary[0]) ? $CatalogLibrary[0]['CatalogLibraryName'] : '',
            'courseTopic' => $courseTopic,
            'collections' => '',
            'vendorId' => $VendorID,
            'vendorName' => $VendorName,
            'libraryId' => $LibraryID,
            'libraryName' => $LibraryName,
            'lessonId' => $LessonID,
            'ej4CourseNumber' => $Ej4CourseNumber,
            'lessonModality' => $LessonModality,
            'hsiProgramID' => $HSIProgramID,
            'lessonLength' => $LessonLength,
            'lessonAffiliations' => $LessonAffiliations,
            'isRecommended' => $isRecommended,
            'locale' => $mainLocale,
            'allLocales' => $allLocales,
            'courseLanguageCategoriesSlug' => '',
            'pricingTier' => $PricingTier,
            'cldImageUrl' => $cldApiImageUrl ?: '',
            'courseImageUrl' => $cdnThumbnailsArray['courseImageUrl'],
            'courseImageThumbUrl' => $cdnThumbnailsArray['courseImageThumbUrl'],
            'courseInformation' => $courseInformation,
            'marketingDescription' => '',
            'courseOutline' => $courseOutlineHtml,
            'courseObjectives' => $courseObjectivesHtml,
            'courseRegulations' => $courseRegulationsHtml,
        ];
    }

    public static function languagesApiArray(): array
    {
        return [
            'french-canadian' => 'French Canadian -',
            'spanish' => 'Spanish -',
        ];
    }

    public function getSlugFromLocalCode(string $localCode): ?string
    {
        $craftLanguageCatSlugs = [
            'english' => 'en_US',
            'spanish' => 'es_US',
            'french-canadian' => 'fr_CA',
            'french' => 'fr_FR',
            'chinese' => 'zh_TW',
            'korean' => 'ko_KR',
            'portuguese' => 'pt_PT',
            'brazilian-portuguese' => 'pt_BR',
            'russian' => 'ru_RU',
            'thai' => 'th_TH',
            'vietnamese' => 'vi_VN',
            'chinese-simplified' => 'zh_CN',
            'german' => 'de_DE',
            'hindi' => 'in_HI',
            'polish' => 'pl_PL',
            'mexican-spanish' => 'es_MX',
            'italian' => 'it_IT',
            'swedish' => 'sv_SE',
            'bosnian' => 'bs_BA',
            'dutch' => 'nl_NL',
            'czech' => 'cs_CS',
            'turkish' => 'tr_TR',
            'spanish-spain' => 'es_ES',
            'filipino' => 'fil',
            'japanese' => 'ja_JP',
        ];
        $slug = array_search($localCode, $craftLanguageCatSlugs, true);

        return $slug !== false ? $slug : null;
    }

    public function isCourseApiRowExists(string $cldId, string $table = 'course_api_data'): bool
    {
        return DB::table($table)->where('cldId', $cldId)->exists();
    }

    public function isCourseBackupApiRowExists(string $cldId): bool
    {
        return DB::table('course_api_data_backup')->where('cldId', $cldId)->exists();
    }

    public function insertBackupCourseApiData(array $courseApiData): void
    {
        $dbTable = 'course_api_data_backup';
        $row = [
            'title' => $courseApiData['title'],
            'salesLibraryTopic' => $courseApiData['salesLibraryTopic'],
            'courseTopic' => $courseApiData['courseTopic'],
            'collections' => $courseApiData['collections'],
            'vendorId' => $courseApiData['vendorId'],
            'vendorName' => $courseApiData['vendorName'],
            'libraryId' => $courseApiData['libraryId'],
            'libraryName' => $courseApiData['libraryName'],
            'lessonId' => $courseApiData['lessonId'],
            'ej4CourseNumber' => $courseApiData['ej4CourseNumber'],
            'lessonModality' => $courseApiData['lessonModality'],
            'hsiProgramID' => $courseApiData['hsiProgramID'],
            'lessonLength' => $courseApiData['lessonLength'],
            'locale' => $courseApiData['locale'],
            'courseLanguageCategoriesSlug' => $courseApiData['courseLanguageCategoriesSlug'],
            'pricingTier' => $courseApiData['pricingTier'],
            'cldImageUrl' => $courseApiData['cldImageUrl'] ?? '',
            'courseImageUrl' => $courseApiData['courseImageUrl'],
            'courseImageThumbUrl' => $courseApiData['courseImageThumbUrl'],
            'courseInformation' => $courseApiData['courseInformation'],
            'marketingDescription' => $courseApiData['marketingDescription'],
            'courseOutline' => $courseApiData['courseOutline'],
            'courseObjectives' => $courseApiData['courseObjectives'],
            'courseRegulations' => $courseApiData['courseRegulations'],
            'parentCldid' => $courseApiData['parentCldid'] ?? null,
            'date_backed_up' => date('Y-m-d H:i:s'),
        ];
        if ($this->isCourseBackupApiRowExists($courseApiData['cldId'])) {
            DB::table($dbTable)->where('cldId', $courseApiData['cldId'])->update($row);
        } else {
            $row['cldId'] = $courseApiData['cldId'];
            DB::table($dbTable)->insert($row);
        }
    }

    public function insertUpdateCourseApiData(array $courseApiData, string $table = 'course_api_data'): void
    {
        if (empty($courseApiData) || ! is_array($courseApiData)) {
            return;
        }
        $dbTable = $table;

        $allLocalesSlugs = [];
        $allLocalesArray = isset($courseApiData['allLocales']) ? @unserialize($courseApiData['allLocales']) : null;
        if (is_array($allLocalesArray) && count($allLocalesArray) > 0) {
            foreach ($allLocalesArray as $locale) {
                $langCode = $locale['LocaleCode'] ?? '';
                $foundCodeSlug = $this->getSlugFromLocalCode($langCode);
                if ($foundCodeSlug) {
                    $allLocalesSlugs[] = $foundCodeSlug;
                }
            }
        }
        $allSlugsDelimited = implode('|', $allLocalesSlugs);

        $row = [
            'title' => $courseApiData['title'],
            'salesLibraryTopic' => $courseApiData['salesLibraryTopic'],
            'courseTopic' => $courseApiData['courseTopic'],
            'collections' => $courseApiData['collections'],
            'vendorId' => $courseApiData['vendorId'],
            'vendorName' => $courseApiData['vendorName'],
            'libraryId' => $courseApiData['libraryId'],
            'libraryName' => $courseApiData['libraryName'],
            'lessonId' => $courseApiData['lessonId'],
            'ej4CourseNumber' => $courseApiData['ej4CourseNumber'],
            'lessonModality' => $courseApiData['lessonModality'],
            'hsiProgramID' => $courseApiData['hsiProgramID'],
            'lessonLength' => $courseApiData['lessonLength'],
            'lessonAffiliations' => $courseApiData['lessonAffiliations'],
            'isRecommended' => $courseApiData['isRecommended'],
            'locale' => $courseApiData['locale'],
            'allLocales' => $allSlugsDelimited,
            'courseLanguageCategoriesSlug' => $courseApiData['courseLanguageCategoriesSlug'],
            'pricingTier' => $courseApiData['pricingTier'],
            'cldImageUrl' => $courseApiData['cldImageUrl'] ?? '',
            'courseImageUrl' => $courseApiData['courseImageUrl'],
            'courseImageThumbUrl' => $courseApiData['courseImageThumbUrl'],
            'courseInformation' => $courseApiData['courseInformation'],
            'marketingDescription' => $courseApiData['marketingDescription'],
            'courseOutline' => $courseApiData['courseOutline'],
            'courseObjectives' => $courseApiData['courseObjectives'],
            'courseRegulations' => $courseApiData['courseRegulations'],
        ];

        if ($this->isCourseApiRowExists($courseApiData['cldId'], $dbTable)) {
            DB::table($dbTable)->where('cldId', $courseApiData['cldId'])->update($row);
        } else {
            $row['cldId'] = $courseApiData['cldId'];
            DB::table($dbTable)->insert($row);
        }
    }

    protected function isWithinMonth(array $course): bool
    {
        if (empty($course['LastUpdate'])) {
            return false;
        }
        $lastUpdate = date('Y-m-d H:i:s', strtotime($course['LastUpdate']));
        $lastMonthDate = (new \DateTime('1 month ago'))->format('Y-m-d H:i:s');

        return $lastUpdate > $lastMonthDate;
    }

    protected function isImageUpdatedWithinMonth($lessonId, string $token): bool
    {
        $thumbnailData = $this->doCurlGetRequest($this->baseUrl.'/api/LessonThumbnail/'.$lessonId, $token);
        if (empty($thumbnailData) || ! is_array($thumbnailData) || ! isset($thumbnailData[0]['UploadDate'])) {
            return false;
        }
        $uploadDate = date('Y-m-d H:i:s', strtotime($thumbnailData[0]['UploadDate']));
        $lastMonthDate = (new \DateTime('1 month ago'))->format('Y-m-d H:i:s');

        return $uploadDate > $lastMonthDate;
    }

    protected function isThumbnailPayloadUpdatedWithinMonth(?array $thumbnailData): bool
    {
        if (empty($thumbnailData) || ! is_array($thumbnailData) || ! isset($thumbnailData[0]['UploadDate']) || empty($thumbnailData[0]['UploadDate'])) {
            return false;
        }

        $uploadDate = date('Y-m-d H:i:s', strtotime($thumbnailData[0]['UploadDate']));
        $lastMonthDate = (new \DateTime('1 month ago'))->format('Y-m-d H:i:s');

        return $uploadDate > $lastMonthDate;
    }

    protected function isEnglishLocalUpdatedWithinMonth($lessonId, string $token): bool
    {
        $cldData = $this->getCldDataRequest($token, $lessonId);
        if (empty($cldData) || ! isset($cldData[0])) {
            return false;
        }
        $course = isset($cldData['LessonID']) ? $cldData : $cldData[0];
        if (empty($course['Locales'])) {
            return false;
        }
        foreach ($course['Locales'] as $locale) {
            if (($locale['LocaleCode'] ?? '') === 'en_US') {
                $lastUpdate = $locale['LastUpdateDate'] ?? null;
                if (empty($lastUpdate)) {
                    return false;
                }
                $lastUpdateDate = date('Y-m-d H:i:s', strtotime($lastUpdate));
                $lastMonthDate = (new \DateTime('1 month ago'))->format('Y-m-d H:i:s');

                return $lastUpdateDate > $lastMonthDate;
            }
        }

        return false;
    }

    protected function isEnglishLocaleUpdatedWithinMonthFromCatalogPayload(?array $catalogPayload): bool
    {
        if (empty($catalogPayload)) {
            return false;
        }

        $course = isset($catalogPayload['LessonID']) ? $catalogPayload : ($catalogPayload[0] ?? null);
        if (empty($course) || empty($course['Locales']) || ! is_array($course['Locales'])) {
            return false;
        }

        foreach ($course['Locales'] as $locale) {
            if (($locale['LocaleCode'] ?? '') === 'en_US') {
                $lastUpdate = $locale['LastUpdateDate'] ?? null;
                if (empty($lastUpdate)) {
                    return false;
                }
                $lastUpdateDate = date('Y-m-d H:i:s', strtotime($lastUpdate));
                $lastMonthDate = (new \DateTime('1 month ago'))->format('Y-m-d H:i:s');

                return $lastUpdateDate > $lastMonthDate;
            }
        }

        return false;
    }

    /**
     * Return CLD IDs that were updated in the last month (for append cron).
     */
    public function apiClidIds(bool $printData = false, bool $includeImageAndLocaleChecks = true): array
    {
        $token = $this->getBearerToken();
        $data = $this->getLastUpdatedList($token);
        if (! $token || empty($data)) {
            return [];
        }

        // 1) Short-circuit: if LastUpdate is within month, select immediately.
        $selected = [];
        $candidates = [];

        foreach ($data as $course) {
            $lessonId = $course['LessonId'] ?? $course['LessonID'] ?? null;
            if (! $lessonId) {
                continue;
            }

            if ($this->isWithinMonth($course)) {
                $selected[(int) $lessonId] = true;

                continue;
            }

            if ($includeImageAndLocaleChecks) {
                $candidates[] = (int) $lessonId;
            }
        }

        if (! $includeImageAndLocaleChecks || empty($candidates)) {
            return array_values(array_map('intval', array_keys($selected)));
        }

        // 2) Pool the thumbnail upload-date check for remaining candidates (chunked to avoid huge pools).
        $chunkSize = 20;
        $chunks = array_chunk($candidates, $chunkSize);
        $chunkIndex = 0;
        echo 'Filter phase: withinMonth='.count($selected).' candidates_for_image_locale_checks='.count($candidates).PHP_EOL;

        foreach ($chunks as $chunk) {
            $chunkIndex++;
            if ($chunkIndex === 1 || ($chunkIndex % 25) === 0) {
                echo "Thumbnail check chunk {$chunkIndex}/".count($chunks).PHP_EOL;
            }
            $responses = Http::pool(function (Pool $pool) use ($chunk, $token) {
                $reqs = [];
                foreach ($chunk as $lessonId) {
                    $reqs[(string) $lessonId] = $pool
                        ->withToken($token)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->connectTimeout(5)
                        ->timeout(20)
                        ->get($this->baseUrl.'/api/LessonThumbnail/'.$lessonId);
                }

                return $reqs;
            });

            foreach ($chunk as $lessonId) {
                if (! isset($responses[(string) $lessonId])) {
                    continue;
                }
                $resp = $responses[(string) $lessonId];
                if ($resp->successful()) {
                    $payload = $resp->json();
                    if ($this->isThumbnailPayloadUpdatedWithinMonth($payload)) {
                        $selected[(int) $lessonId] = true;
                    }
                }
            }
        }

        // 3) Pool the catalog locale update check for those not yet selected.
        $remaining = array_values(array_filter($candidates, fn ($id) => empty($selected[(int) $id])));
        $chunks = array_chunk($remaining, $chunkSize);
        $chunkIndex = 0;
        foreach ($chunks as $chunk) {
            $chunkIndex++;
            if ($chunkIndex === 1 || ($chunkIndex % 25) === 0) {
                echo "Catalog locale check chunk {$chunkIndex}/".count($chunks).PHP_EOL;
            }
            $responses = Http::pool(function (Pool $pool) use ($chunk, $token) {
                $reqs = [];
                foreach ($chunk as $lessonId) {
                    $reqs[(string) $lessonId] = $pool
                        ->withToken($token)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->connectTimeout(5)
                        ->timeout(20)
                        ->get($this->baseUrl.'/api/catalog/'.$lessonId);
                }

                return $reqs;
            });

            foreach ($chunk as $lessonId) {
                if (! isset($responses[(string) $lessonId])) {
                    continue;
                }
                $resp = $responses[(string) $lessonId];
                if ($resp->successful()) {
                    $payload = $resp->json();
                    if ($this->isEnglishLocaleUpdatedWithinMonthFromCatalogPayload($payload)) {
                        $selected[(int) $lessonId] = true;
                    }
                }
            }
        }

        return array_values(array_map('intval', array_keys($selected)));
    }

    public function backUpPreviousFeedData(): void
    {
        $dbTable = 'course_api_data';
        $rows = DB::table($dbTable)->get();
        foreach ($rows as $row) {
            $course = [
                'title' => $row->title,
                'cldId' => $row->cldId,
                'salesLibraryTopic' => $row->salesLibraryTopic,
                'courseTopic' => $row->courseTopic,
                'collections' => $row->collections,
                'vendorId' => $row->vendorId,
                'vendorName' => $row->vendorName,
                'libraryId' => $row->libraryId,
                'libraryName' => $row->libraryName,
                'lessonId' => $row->lessonId,
                'ej4CourseNumber' => $row->ej4CourseNumber,
                'lessonModality' => $row->lessonModality,
                'hsiProgramID' => $row->hsiProgramID,
                'lessonLength' => $row->lessonLength,
                'lessonAffiliations' => $row->lessonAffiliations,
                'isRecommended' => $row->isRecommended,
                'locale' => $row->locale,
                'allLocales' => $row->allLocales,
                'courseLanguageCategoriesSlug' => $row->courseLanguageCategoriesSlug,
                'pricingTier' => $row->pricingTier,
                'cldImageUrl' => $row->cldImageUrl ?? '',
                'courseImageUrl' => $row->courseImageUrl,
                'courseImageThumbUrl' => $row->courseImageThumbUrl,
                'courseInformation' => $row->courseInformation,
                'marketingDescription' => $row->marketingDescription,
                'courseOutline' => $row->courseOutline,
                'courseObjectives' => $row->courseObjectives,
                'courseRegulations' => $row->courseRegulations,
                'parentCldid' => $row->parentCldid ?? null,
            ];
            $this->insertBackupCourseApiData($course);
            DB::table($dbTable)->where('capdid', $row->capdid)->delete();
        }
    }

    public function findParentEnglishCldid(string $parentTitle, string $table = 'course_api_data'): string|false
    {
        $dbTable = $table;
        $row = DB::table($dbTable)->where('title', $parentTitle)->first();

        return $row ? $row->cldId : false;
    }

    public function updateApiCourseByCldid(string $cldId, string $option, $value, string $table = 'course_api_data'): void
    {
        $dbTable = $table;
        DB::table($dbTable)->where('cldId', $cldId)->update([$option => $value]);
    }

    public function getCourseOptionByClidid(string $cldId, string $option, string $table = 'course_api_data'): ?string
    {
        $dbTable = $table;
        $row = DB::table($dbTable)->where('cldId', $cldId)->first();

        return $row ? ($row->$option ?? null) : null;
    }

    public function matchParentToChildLanguages(string $table = 'course_api_data'): void
    {
        $dbTable = $table;
        $data = DB::table($dbTable)->get();
        $languagesArray = self::languagesApiArray();
        foreach ($data as $row) {
            $childCldid = $row->cldId;
            foreach ($languagesArray as $slug => $language) {
                if (str_contains($row->title ?? '', $language)) {
                    $parentTitle = trim(str_replace($language, '', $row->title ?? ''));
                    $parentCldid = $this->findParentEnglishCldid($parentTitle, $table);
                    if ($parentCldid) {
                        $this->updateApiCourseByCldid($childCldid, 'parentCldid', $parentCldid, $table);
                        $currentParentLanguageSlugs = $this->getCourseOptionByClidid($parentCldid, 'courseLanguageCategoriesSlug', $table);
                        $this->updateApiCourseByCldid($parentCldid, 'courseLanguageCategoriesSlug', ($currentParentLanguageSlugs ?? '').'|'.$slug, $table);
                    }
                }
            }
        }
    }

    /**
     * Trigger Craft Feed Me “run task” URL. Uses a long read timeout because imports can run for minutes.
     * Logs a redacted URL (passkey masked). Detects common “login page” responses when HTTP status is still 200.
     */
    public function triggerFeedMeAction(string $feedMeUrl): array
    {
        $safeUrl = preg_replace('/([?&])passkey=[^&]*/', '$1passkey=***', $feedMeUrl) ?? $feedMeUrl;

        try {
            $response = Http::withHeaders([
                'Accept' => '*/*',
                'User-Agent' => 'HSI-Hub-CLD-Sync/1.0 (Laravel Http; FeedMe)',
            ])
                ->connectTimeout(30)
                ->timeout((int) config('cld_api.feedme.timeout_seconds', 300))
                ->withOptions([
                    'verify' => true,
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true,
                    ],
                ])
                ->get($feedMeUrl);
        } catch (\Throwable $e) {
            Log::error('FeedMe request failed', ['url' => $safeUrl, 'error' => $e->getMessage()]);

            return [
                'ok' => false,
                'http_code' => 0,
                'response_excerpt' => '',
                'error' => $e->getMessage(),
                'suspected_login_interstitial' => false,
            ];
        }

        $body = $response->body();
        $code = $response->status();
        $okHttp = $response->successful();
        $plain = strip_tags($body);
        $suspectedLogin = $okHttp && $this->feedMeResponseLooksLikeLoginPage($body, $plain);

        if ($suspectedLogin) {
            Log::warning('FeedMe response may be a login or CP page, not a feed run', [
                'url' => $safeUrl,
                'http_code' => $code,
                'body_length' => strlen($body),
            ]);
        } else {
            Log::info('FeedMe request finished', [
                'url' => $safeUrl,
                'http_code' => $code,
                'body_length' => strlen($body),
            ]);
        }

        return [
            'ok' => $okHttp && ! $suspectedLogin,
            'http_code' => $code,
            'response_excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', $plain)) ?: '(empty body)', 600),
            'error' => null,
            'suspected_login_interstitial' => $suspectedLogin,
        ];
    }

    protected function feedMeResponseLooksLikeLoginPage(string $htmlBody, string $plainText): bool
    {
        $lower = strtolower($htmlBody);

        return str_contains($lower, 'type="password"')
            || str_contains($lower, 'name="password"')
            || str_contains($lower, 'cp-login')
            || (str_contains($lower, 'login') && str_contains($lower, '<form'))
            || str_contains($plainText, 'Log in to')
            || str_contains($plainText, 'Sign In');
    }

    /**
     * Main cron-style sync: fetch CLD IDs updated in last month, backup current data, fetch each course, insert/update, match languages, optionally run FeedMe.
     * For one-off singles (specific IDs) use runSyncWithManualList() instead.
     */
    public function cronJobGenerateAddUpdateCldApiDataFromList(
        array $manualList = [],
        int $feedId = 70,
        bool $doSingleBatch = false,
        bool $runFeedMe = true,
        bool $includeImageAndLocaleChecks = true
    ): CldSyncResult {
        if ($doSingleBatch && $manualList === []) {
            return new CldSyncResult('singles', 0, 0, [], null, 'No lesson IDs provided.');
        }

        if (! $doSingleBatch) {
            $table = 'course_api_data';
            $cldids = $this->apiClidIds(false, $includeImageAndLocaleChecks);
            if (empty($cldids)) {
                return new CldSyncResult('full', 0, 0, [], null, 'No CLD lesson IDs matched the sync criteria (empty list).');
            }
            echo 'CLD IDs to process: '.count($cldids).PHP_EOL;
            $this->backUpPreviousFeedData();
            if (! empty($manualList)) {
                $cldids = array_merge($cldids, $manualList);
            }
        } else {
            $cldids = $manualList;
            $table = 'course_api_data_singles';
            DB::statement('TRUNCATE TABLE '.$table);
            try {
                $this->getBearerToken();
            } catch (\Throwable $e) {
                Log::error('CLD auth failed before singles processing', ['error' => $e->getMessage()]);

                return new CldSyncResult('singles', count($cldids), 0, [], null, 'CLD authentication failed: '.$e->getMessage());
            }
        }

        $failures = [];
        $succeeded = 0;
        $i = 0;
        foreach ($cldids as $cldid) {
            $i++;
            if ($i === 1 || ($i % 25) === 0) {
                echo "Processing {$i}/".count($cldids)." (LessonID {$cldid})".PHP_EOL;
            }
            try {
                $courseData = $this->getCourseCldData($cldid);
            } catch (\Throwable $e) {
                Log::error('CLD getCourseCldData threw', ['lesson_id' => $cldid, 'error' => $e->getMessage()]);
                $failures[] = ['lesson_id' => (int) $cldid, 'reason' => $e->getMessage()];

                continue;
            }
            if ($courseData === null) {
                $failures[] = ['lesson_id' => (int) $cldid, 'reason' => 'Empty catalog response or missing LessonID (check API key / lesson exists).'];

                continue;
            }
            $this->insertUpdateCourseApiData($courseData, $table);
            $succeeded++;
        }

        $this->matchParentToChildLanguages($table);

        $feedMeResult = null;
        if ($runFeedMe && config('cld_api.feedme.passkey')) {
            $baseUrl = config('cld_api.feedme.prod_url');
            $url = $baseUrl.'?direct=1&feedId='.$feedId.'&passkey='.config('cld_api.feedme.passkey');
            $feedMeResult = $this->triggerFeedMeAction($url);
        }

        return new CldSyncResult(
            $doSingleBatch ? 'singles' : 'full',
            count($cldids),
            $succeeded,
            $failures,
            $feedMeResult,
            null,
        );
    }
}
