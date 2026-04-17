<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');
// include(dirname(dirname(__FILE__)) . '/vendor/autoload.php');
require dirname(dirname(__FILE__)).'/classes/generic.class.php';

// require_once 'meekrodb.2.4.class.php';

class CldApi extends Generic
{
    protected $apiEnpointBaseUrl;

    private function downloadBinary($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTPHEADER => [
                'Accept: image/*,*/*;q=0.8',
            ],
            CURLOPT_USERAGENT => 'apis-md/1.0 (course-image-fetch)',
        ]);

        $data = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr = $curlErrNo ? curl_error($ch) : '';
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return [
            'ok' => ($data !== false) && $curlErrNo === 0 && $httpCode >= 200 && $httpCode < 300,
            'data' => $data,
            'httpCode' => $httpCode,
            'contentType' => $contentType,
            'curlErrNo' => $curlErrNo,
            'curlErr' => $curlErr,
        ];
    }

    private function describePath($path)
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

    public function __construct()
    {
        $this->apiEnpointBaseUrl = 'https://cldapi.hsiplatform.com';
    }

    public function sendEmailError($errMsg)
    {
        $to = 'hochoa@gmail.com';
        $subject = 'There was an Courses API Error';
        $message = $errMsg;
        $headers = 'From: krxhrbsvzj@922328.cloudwaysstagingapps.com'."\r\n".
            'Reply-To: krxhrbsvzj@922328.cloudwaysstagingapps.com'."\r\n".
            'X-Mailer: PHP/'.phpversion();

        mail($to, $subject, $message, $headers);
    }

    public function getBearerToken()
    {

        $database = parent::db();

        $data = $database->select('cld_api_tokens', '*');
        foreach ($data as $row) {
            $lastTokenDateTime = $row['datetime_created'];
            $oldToken = $row['token'];
        }

        if (! empty($oldToken) && ! empty($lastTokenDateTime) && time() - strtotime($lastTokenDateTime) < 3601 * 4) {
            // echo 'token created less 4 hours ago';
            // Use Old Token From DB
            return $oldToken;
        }

        // echo 'token created more than 4 hours ago';
        // Get New Token and store in DB
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiEnpointBaseUrl.'/api/auth/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
        	"Admin_ID": "HSI_Marketing",
        	"Password": "rvm6mqm5PKB!rzb0khp" 
        }',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        // echo $response;
        $responseArray = json_decode($response, true);
        // Update DB with new Token
        if (! empty($responseArray['Token'])) {
            $database->update(
                'cld_api_tokens',
                [
                    'token' => $responseArray['Token'],
                    'datetime_created' => date('Y-m-d H:i:s'),
                ],
                ['cldatkid' => 1]
            );

            return $responseArray['Token'];
        } else {
            echo 'no token';
            exit();
        }
    }

    public function doCurlGetRequest($requestUrl, $token)
    {
        $curl = curl_init();

        $authorization = 'Authorization: Bearer '.$token;

        curl_setopt_array($curl, [
            CURLOPT_URL => $requestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', $authorization],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        // echo $response;
        $responseArray = json_decode($response, true);

        return $responseArray;
    }

    public function downloadRemoteSrcToLocal($remoteSrc, $cldid, $lessonName)
    {
        $newfileName = $cldid.'-'.parent::slugify($lessonName).'.jpeg';
        $target_dir = dirname(dirname(__FILE__)).'/uploads/course-images/';
        $localPath = $target_dir;
        $img = $localPath.$newfileName;
        if (! empty($remoteSrc)) {
            // Ensure directory exists before writing
            if (! parent::ensureDirectoryExists($target_dir)) {
                $desc = $this->describePath($target_dir);
                echo "Error: Target directory missing/unusable for {$cldid}: {$target_dir}".PHP_EOL;
                error_log("Failed to create or access directory: {$target_dir}. Details: ".json_encode($desc));

                return false;
            }

            if (! is_writable($target_dir)) {
                $desc = $this->describePath($target_dir);
                echo "Error: Target directory not writable for {$cldid}: {$target_dir} (perms {$desc['perms']})".PHP_EOL;
                error_log("Directory not writable: {$target_dir}. Details: ".json_encode($desc));

                return false;
            }

            $res = $this->downloadBinary($remoteSrc);
            if (! $res['ok']) {
                echo "Error: Image download failed for {$cldid}: httpCode={$res['httpCode']} curlErrNo={$res['curlErrNo']}".PHP_EOL;
                error_log("Failed to download remote image: {$remoteSrc}. httpCode={$res['httpCode']} contentType={$res['contentType']} curlErrNo={$res['curlErrNo']} curlErr={$res['curlErr']}");

                return false;
            }

            $bytes = is_string($res['data']) ? strlen($res['data']) : 0;
            if ($bytes < 256) {
                error_log("Downloaded image payload suspiciously small ({$bytes} bytes) for {$remoteSrc}. httpCode={$res['httpCode']} contentType={$res['contentType']}");
            }

            // Write atomically to avoid partial/locked files on shared storage.
            // If the destination already exists but isn't writable (common on deployments),
            // try to chmod/unlink before writing.
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
                $dirDesc = $this->describePath($target_dir);
                $fileDesc = $this->describePath($img);
                echo "Error: Failed to write image for {$cldid}: {$img}".PHP_EOL;
                error_log("Failed to write image file: {$img}. lastError=".json_encode($lastErr).' dir='.json_encode($dirDesc).' file='.json_encode($fileDesc));

                return false;
            }

            // $this->updateInsertOptimizedSrcCourseImage($cldid, $img);
            // echo 'Download Local: ' . $newfileName . PHP_EOL;
            return $img;
        }

        return false;
    }

    public function optimizeLargeImage($hqPathandFilename, $cldid)
    {
        $filename = 'lg-'.basename($hqPathandFilename);
        $pathToOutput = dirname(dirname(__FILE__)).'/uploads/course-images/optimized/'.$filename;
        if (! empty($hqPathandFilename)) {
            $generatedThumb = parent::generate_image_thumbnail($hqPathandFilename, $pathToOutput, 976, 549);
            if ($generatedThumb) {
                // echo 'Optimized Image: ' . $filename . PHP_EOL;
                return $pathToOutput;
            } else {
                // echo 'Did Not Optimized Image: ' . $cldid . PHP_EOL;
                return false;
            }
        }
    }

    public function optimizeSmallImage($lgPathAndFilename, $cldid)
    {
        $filename = str_replace('lg-C', 'sm-C', basename($lgPathAndFilename));
        $pathToOutput = dirname(dirname(__FILE__)).'/uploads/course-images/optimized/small/'.$filename;
        if (! empty($lgPathAndFilename)) {
            $generatedThumb = parent::generate_image_thumbnail($lgPathAndFilename, $pathToOutput, 226, 127);
            if ($generatedThumb) {
                // echo 'Optimized Small Image: ' . $filename . PHP_EOL;
                return $pathToOutput;
            } else {
                // echo 'Did Not Optimized Image: ' . $cldid . PHP_EOL;
                return false;
            }
        }
    }

    public function processCldImageUrlToCdn($cldImageUrl, $cldid, $lessonName)
    {
        $imageData = [];
        $newLocalHqPathAndFilename = $this->downloadRemoteSrcToLocal($cldImageUrl, $cldid, $lessonName);
        if (! $newLocalHqPathAndFilename) {
            echo "Error: Failed to download image from {$cldImageUrl} for {$cldid}".PHP_EOL;

            return $imageData;
        }
        $newLocalLgPathAndFilename = $this->optimizeLargeImage($newLocalHqPathAndFilename, $cldid);
        if (! $newLocalLgPathAndFilename) {
            echo "Error: Failed to optimize large image for {$cldid}".PHP_EOL;

            return $imageData;
        }
        $newLocalSmPathAndFilename = $this->optimizeSmallImage($newLocalLgPathAndFilename, $cldid);
        if (! $newLocalSmPathAndFilename) {
            echo "Error: Failed to optimize small image for {$cldid}".PHP_EOL;

            return $imageData;
        }
        $filenameLG = basename($newLocalLgPathAndFilename);
        $lgCdnUrl = parent::uploadToDoSpaces($newLocalLgPathAndFilename, $filenameLG);
        if (! $lgCdnUrl) {
            echo "Error: Failed to upload large image to CDN for {$cldid}".PHP_EOL;

            return $imageData;
        }
        $filenameSM = basename($newLocalSmPathAndFilename);
        $smCdnUrl = parent::uploadToDoSpaces($newLocalSmPathAndFilename, $filenameSM);
        if (! $smCdnUrl) {
            echo "Error: Failed to upload small image to CDN for {$cldid}".PHP_EOL;

            return $imageData;
        }
        $imageData = [
            'localHq' => $newLocalHqPathAndFilename,
            'localLg' => $newLocalLgPathAndFilename,
            'localSm' => $newLocalSmPathAndFilename,
            'lgCdnUrl' => $lgCdnUrl,
            'smCdnUrl' => $smCdnUrl,
        ];

        return $imageData;
    }

    public function testCldThumbnailUrl($LessonID)
    {
        $token = $this->getBearerToken();
        $url = $this->apiEnpointBaseUrl.'/api/LessonThumbnail/'.$LessonID;
        $thumbnailData = $this->doCurlGetRequest($url, $token);
        $thumbnailId = '';
        $dimensions = '1920x1080';
        echo '<pre>';
        print_r($thumbnailData);
        echo '</pre>';
        exit();
        foreach ($thumbnailData as $data) {
            if ($data['IsPrimary'] == 1 || $data['IsPrimary'] == true) {
                $thumbnailId = $data['ThumbnailID'];
                $dimensions = $data['ImageDimensions'];
                break;
            } else {
                $thumbnailId = $data['ThumbnailID'];
                $dimensions = $data['ImageDimensions'];
            }
        }
        if (! empty($thumbnailId)) {
            return $this->apiEnpointBaseUrl.'/api/preview/thumbnails/'.$thumbnailId.'?dimensions='.$dimensions;
        }

        return false;
    }

    public function getCldThumbnailUrl($LessonID, $token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/LessonThumbnail/'.$LessonID;
        $thumbnailData = $this->doCurlGetRequest($url, $token);
        $thumbnailId = '';
        $dimensions = '1920x1080';
        if (! is_array($thumbnailData) || empty($thumbnailData)) {
            return false;
        }
        foreach ($thumbnailData as $data) {
            if (isset($data['IsPrimary']) && ($data['IsPrimary'] == 1 || $data['IsPrimary'] == true)) {
                $thumbnailId = $data['ThumbnailID'] ?? '';
                $dimensions = $data['ImageDimensions'] ?? '1920x1080';
                break;
            } else {
                $thumbnailId = $data['ThumbnailID'] ?? '';
                $dimensions = $data['ImageDimensions'] ?? '1920x1080';
            }
        }
        if (! empty($thumbnailId)) {
            return $this->apiEnpointBaseUrl.'/api/preview/thumbnails/'.$thumbnailId.'?dimensions='.$dimensions;
        }

        return false;
    }

    public function getLastUpdatedListAll($token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/marketing/courses';

        return $this->doCurlGetRequest($url, $token);
    }

    public function getLastUpdatedList($token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/marketing/courses';

        return $this->doCurlGetRequest($url, $token);
    }

    public function getCourseTopic($CatalogTopicId, $token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/CatalogTopic/'.$CatalogTopicId;

        return $this->doCurlGetRequest($url, $token);
    }

    public function getSalesLibraryTopic($CatalogLibraryId, $token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/CatalogLibrary/'.$CatalogLibraryId;

        return $this->doCurlGetRequest($url, $token);
    }

    public function getCldDataRequest($token, $cldid)
    {
        $url = $this->apiEnpointBaseUrl.'/api/catalog/'.$cldid;

        return $this->doCurlGetRequest($url, $token);
    }

    public function getFirstLocaleFromLocales($localesArray)
    {
        if (! is_array($localesArray)) {
            return '';
        }
        foreach ($localesArray as $locale) {
            return $locale['LocaleCode'];
        }

        return '';
    }

    public function getCourseOutline($cldid, $token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/LessonSection?text='.$cldid;

        return $this->doCurlGetRequest($url, $token);
    }

    public function getCourseObjectives($cldid, $token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/lessonobjective/primary/'.$cldid;

        return $this->doCurlGetRequest($url, $token);
    }

    public function getCourseObjectivesV1($cldid, $token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/LessonObjective?text='.$cldid;

        return $this->doCurlGetRequest($url, $token);
    }

    public function getMarketingCoursesInactive($token)
    {

        $url = $this->apiEnpointBaseUrl.'/api/marketing/courses/inactive';

        return $this->doCurlGetRequest($url, $token);
    }

    public function getCourseRegulations($cldid, $token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/RegulatoryRequirement?text='.$cldid;

        return $this->doCurlGetRequest($url, $token);
    }

    public function convertSpecialChar($text)
    {
        // $result = mb_detect_encoding($text . " ", "UTF-8,CP1252") == "UTF-8" ? iconv("UTF-8", "utf-8//TRANSLIT", $text) : $text;
        $result = preg_replace('/&([a-z])[a-z]+;/i', '$1', htmlentities($text));

        return $result;
    }

    public function getCourseCldDataExtra($cldid)
    {
        $token = $this->getBearerToken();

        if (isset($_GET['lessonId'])) {
            $cldid = $_GET['lessonId'];
        }
        $clddata = $this->getCldDataRequest($token, $cldid);

        // echo '<pre>';
        // print_r($clddata);
        // echo '</pre>';
        // exit; // stop here for now

        if (isset($clddata[0])) {
            $item = $clddata[0];
            $CLDID = 'CLD-'.$item['LessonID'];
            $LessonName = $item['LessonName'];
            $LessonAffiliations = serialize($item['LessonAffiliations']);
            $isRecommended = $item['isRecommended'];
            $PricingTier = $item['PricingTier'];
            $allLocales = serialize($item['Locales']);
            echo 'cldid '.$CLDID.' - PricingTier'.$item['PricingTier'].PHP_EOL;
            $courseItem = [
                'title' => $LessonName,
                'cldId' => $CLDID,
                'lessonAffiliations' => $LessonAffiliations,
                'isRecommended' => $isRecommended,
                'allLocales' => $allLocales,
                'pricingTier' => $PricingTier,
            ];

            // echo '<pre>';
            // print_r($courseItem);
            // echo '</pre>';

            return $courseItem;
        }

        return false;
    }

    public function getCourseCldData($cldid = 15933, $printArray = false)
    {
        $token = $this->getBearerToken();

        if (isset($_GET['lessonId'])) {
            $cldid = $_GET['lessonId'];
        }
        $clddata = $this->getCldDataRequest($token, $cldid);
        if (empty($clddata)) {
            return null;
        }
        // API may return single object for /api/catalog/{id} or array of one item
        $item = isset($clddata['LessonID']) ? $clddata : (isset($clddata[0]) ? $clddata[0] : null);
        if (empty($item) || ! isset($item['LessonID'])) {
            return null;
        }
        // if ($printArray) {
        //     $cldApiImageUrl = $this->getCldThumbnailUrl($item['LessonID'], $token);
        //     echo '<pre>';
        //     print_r($clddata);
        //     echo '</pre>';
        //     echo '<pre>';
        //     echo $cldApiImageUrl;
        //     echo '</pre>';
        //     exit();
        // }
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
        $courseInformation = $item['LessonDescription'];
        $CatalogTopic = $this->getCourseTopic($item['CatalogTopicId'], $token);
        $CatalogLibrary = $this->getSalesLibraryTopic($item['CatalogLibraryId'], $token);
        $cldApiImageUrl = $this->getCldThumbnailUrl($item['LessonID'], $token);
        $cdnThumbnailsArray = ['courseImageUrl' => '', 'courseImageThumbUrl' => ''];
        if ($cldApiImageUrl) {
            echo "Processing image for {$CLDID}: {$cldApiImageUrl}".PHP_EOL;
            $cdnImages = $this->processCldImageUrlToCdn($cldApiImageUrl, $CLDID, $LessonName);
            if (! empty($cdnImages) && isset($cdnImages['lgCdnUrl']) && isset($cdnImages['smCdnUrl'])) {
                $cdnThumbnailsArray = ['courseImageUrl' => $cdnImages['lgCdnUrl'], 'courseImageThumbUrl' => $cdnImages['smCdnUrl']];
                echo "Image URLs generated for {$CLDID}: lg={$cdnImages['lgCdnUrl']}, sm={$cdnImages['smCdnUrl']}".PHP_EOL;
            } else {
                echo "Warning: Image processing failed for {$CLDID}. processCldImageUrlToCdn returned: ".print_r($cdnImages, true).PHP_EOL;
            }
        } else {
            echo "No thumbnail URL found for {$CLDID} (LessonID: {$item['LessonID']})".PHP_EOL;
            $cldApiImageUrl = '';
        }

        // Course Outline - LessonSection

        $CourseOutline = $this->getCourseOutline($cldid, $token);
        $courseOutlineHtml = '';
        if (is_array($CourseOutline) && count($CourseOutline) > 0) {
            $courseOutlineHtml = '<ul>';
            foreach ($CourseOutline as $outline) {
                if (! empty($outline['SectionName']) && strlen($outline['SectionName']) == mb_strlen($outline['SectionName'], 'utf-8')) {
                    // string contains only english letters & digits
                    if ($outline['LocaleID'] == 1) {
                        $courseOutlineHtml .= '<li>'.$this->convertSpecialChar($outline['SectionName']).'</li>';
                    }
                }
            }
            $courseOutlineHtml .= '</ul>';
            if ($courseOutlineHtml == '<ul></ul>') {
                $courseOutlineHtml = '';
            }
        }
        // echo '<pre>';
        // print_r($CourseOutline);
        // echo '</pre>';

        // Course Objectives
        $CourseObjectives = $this->getCourseObjectives($cldid, $token);
        $courseObjectivesHtml = '';
        if (is_array($CourseObjectives) && count($CourseObjectives) > 0) {
            $courseObjectivesHtml = '<ul>';
            foreach ($CourseObjectives as $objective) {
                $courseObjectivesHtml .= '<li>'.$this->convertSpecialChar($objective['ObjectiveText']).'</li>';
            }
            $courseObjectivesHtml .= '</ul>';
        }
        // echo '<pre>';
        // print_r($CourseObjectives);
        // echo '</pre>';
        $CourseRegulations = $this->getCourseRegulations($cldid, $token);
        $courseRegulationsHtml = '';
        if (count($CourseRegulations) > 0) {
            $courseRegulationsHtml = '<ul>';
            foreach ($CourseRegulations as $regulation) {
                $courseRegulationsHtml .= '<li>'.$this->convertSpecialChar($regulation['Requirement']).'</li>';
            }
            $courseRegulationsHtml .= '</ul>';
        }
        // echo '<pre>';
        // print_r($regulations);
        // echo '</pre>';

        $courseTopic = isset($CatalogTopic[0]) ? $CatalogTopic[0]['CatalogTopicName'] : '';
        if ($courseTopic == 'Psycology') {
            $courseTopic = 'Psychology';
        }

        $courseItem = [
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
            'cldImageUrl' => $cldApiImageUrl,
            'courseImageUrl' => $cdnThumbnailsArray['courseImageUrl'],
            'courseImageThumbUrl' => $cdnThumbnailsArray['courseImageThumbUrl'],
            'courseInformation' => $courseInformation,
            'marketingDescription' => '',
            'courseOutline' => $courseOutlineHtml,
            'courseObjectives' => $courseObjectivesHtml,
            'courseRegulations' => $courseRegulationsHtml,
        ];

        // echo '<pre>';
        // print_r($courseItem);
        // echo '</pre>';

        return $courseItem;
    }

    public function buildXmlRow($row, $xmlString)
    {
        $xmlString .= '<course>';
        $xmlString .= '<title>
        <![CDATA['.$row['title'].']]>
    </title>
    <cldId>'.$row['cldId'].'</cldId>
    <salesLibraryTopic>
        <![CDATA['.$row['salesLibraryTopic'].']]>
    </salesLibraryTopic>
    <courseTopic>
        <![CDATA['.$row['courseTopic'].']]>
    </courseTopic>
    <Collections>'.$row['collections'].'</Collections>
    <vendorId>'.$row['vendorId'].'</vendorId>
    <vendorName>'.$row['vendorName'].'</vendorName>
    <libraryId>'.$row['libraryId'].'</libraryId>
    <libraryName> <![CDATA['.$row['libraryName'].']]></libraryName>
    <lessonId>'.$row['lessonId'].'</lessonId>
    <ej4CourseNumber>'.$row['ej4CourseNumber'].'</ej4CourseNumber>
    <lessonModality>'.$row['lessonModality'].'</lessonModality>
    <hsiProgramID>'.$row['hsiProgramID'].'</hsiProgramID>
    <lessonLength>'.$row['lessonLength'].'</lessonLength>
    <locale>'.$row['locale'].'</locale>
    <courseLanguageCategoriesSlug>'.$row['courseLanguageCategoriesSlug'].'</courseLanguageCategoriesSlug>
    <PricingTier>'.$row['pricingTier'].'</PricingTier>
    <courseImageUrl>'.$row['courseImageUrl'].'</courseImageUrl>
    <courseImageThumbUrl>'.$row['courseImageThumbUrl'].'</courseImageThumbUrl>
    <courseInformation>
        <![CDATA['.$row['courseInformation'].']]>
    </courseInformation>
    <marketingDescription>
        <![CDATA['.$row['marketingDescription'].']]>
    </marketingDescription>
    <courseOutline>
    <![CDATA['.$row['courseOutline'].']]>
    </courseOutline>
    <courseObjectives>
    <![CDATA['.$row['courseObjectives'].']]>
    </courseObjectives>
    <courseRegulations>
    <![CDATA['.$row['courseRegulations'].']]>
    </courseRegulations>
</course>';

        return $xmlString;
    }

    /**
     * Build XML feed from course_api_data_singles (same column layout as course_api_data).
     * Use this for the singles feed; Generic::fullXmlFeedSingles uses course_data columns and is wrong for this table.
     */
    public function fullXmlFeedSingles()
    {
        $database = parent::db();
        header('Content-Type: application/xml; charset=utf-8');

        $xmlString = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
        $xmlString .= '<root><courses>';

        $data = $database->select('course_api_data_singles', '*');
        foreach ($data as $row) {
            $xmlString = $this->buildXmlRow($row, $xmlString);
        }

        $xmlString .= '</courses></root>';

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xmlString);
        $dom->save(dirname(dirname(__FILE__)).'/xml/coursesFeedSingles.xml');

        echo $dom->saveXML();
    }

    public function isCourseToDeleteBackupApiRowExists($cldid)
    {
        $database = parent::db();

        $count = $database->count('course_api_to_delete_backup', [
            'cldid' => $cldid,
        ]);

        $exists = ($count > 0) ? true : false;

        return $exists;
    }

    public function isCourseApiRowExists($cldId, $env = 'prod', $table = null)
    {
        $database = parent::db();

        if ($table !== null) {
            $dbTable = $table;
        } elseif ($env != 'prod') {
            $dbTable = 'course_api_data_staging';
        } else {
            $dbTable = 'course_api_data';
        }

        $count = $database->count($dbTable, [
            'cldId' => $cldId,
        ]);

        $exists = ($count > 0) ? true : false;

        return $exists;
    }

    public function isCourseBackupApiRowExists($cldId, $env = 'prod')
    {
        $database = parent::db();

        if ($env != 'prod') {
            $dbTable = 'course_api_data_backup_staging';
        } else {
            $dbTable = 'course_api_data_backup';
        }

        $count = $database->count($dbTable, [
            'cldId' => $cldId,
        ]);

        $exists = ($count > 0) ? true : false;

        return $exists;
    }

    public function insertBackupCourseApiData($courseApiData, $env = 'prod')
    {
        $database = parent::db();

        if ($env != 'prod') {
            $dbTable = 'course_api_data_backup_staging';
        } else {
            $dbTable = 'course_api_data_backup';
        }

        if ($this->isCourseBackupApiRowExists($courseApiData['cldId'], $env)) {
            // Row Exists - Update
            $database->update(
                $dbTable,
                [
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
                    'cldImageUrl' => $courseApiData['cldImageUrl'],
                    'courseImageUrl' => $courseApiData['courseImageUrl'],
                    'courseImageThumbUrl' => $courseApiData['courseImageThumbUrl'],
                    'courseInformation' => $courseApiData['courseInformation'],
                    'marketingDescription' => $courseApiData['marketingDescription'],
                    'courseOutline' => $courseApiData['courseOutline'],
                    'courseObjectives' => $courseApiData['courseObjectives'],
                    'courseRegulations' => $courseApiData['courseRegulations'],
                    'parentCldid' => $courseApiData['parentCldid'],
                    'date_backed_up' => date('Y-m-d H:i:s'),
                ],
                ['cldId' => $courseApiData['cldId']]
            );
        } else {
            // Row Doesn't Exist Insert
            $database->insert($dbTable, [
                'title' => $courseApiData['title'],
                'cldId' => $courseApiData['cldId'],
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
                'courseImageUrl' => $courseApiData['courseImageUrl'],
                'courseImageThumbUrl' => $courseApiData['courseImageThumbUrl'],
                'courseInformation' => $courseApiData['courseInformation'],
                'marketingDescription' => $courseApiData['marketingDescription'],
                'courseOutline' => $courseApiData['courseOutline'],
                'courseObjectives' => $courseApiData['courseObjectives'],
                'courseRegulations' => $courseApiData['courseRegulations'],
                'parentCldid' => $courseApiData['parentCldid'],
                'date_backed_up' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function getSlugFromLocalCode($localCode)
    {
        // tagalog
        // arabic
        // farsi
        // cantonese
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

        return array_search($localCode, $craftLanguageCatSlugs, true) ?: null;
    }

    public function insertUpdateCourseExtraApiData($courseApiData)
    {
        $database = parent::db();

        $dbTable = 'course_api_append_data';

        $allLocalesSlugs = [];
        $allLocalesArray = unserialize($courseApiData['allLocales']);
        if (count($allLocalesArray) > 0) {
            foreach ($allLocalesArray as $locale) {
                $langCode = $locale['LocaleCode'];
                $foundCodeSlug = $this->getSlugFromLocalCode($langCode);
                if ($foundCodeSlug) {
                    array_push($allLocalesSlugs, $foundCodeSlug);
                }
            }
        }

        $allSlugsDelimited = implode('|', $allLocalesSlugs);

        $database->update(
            $dbTable,
            [
                'lessonAffiliation' => $courseApiData['lessonAffiliations'],
                'recommended' => $courseApiData['isRecommended'],
                'languages' => $allSlugsDelimited,
                'pricingTier' => $courseApiData['pricingTier'],
                'status' => 'filled',
                'last_updated' => date('Y-m-d H:i:s'),
            ],
            ['cldId' => $courseApiData['cldId']]
        );
    }

    public function insertUpdateCourseApiData($courseApiData, $env = 'prod', $table = 'course_api_data')
    {
        if (empty($courseApiData) || ! is_array($courseApiData)) {
            return;
        }
        $database = parent::db();

        if ($env != 'prod') {
            $dbTable = 'course_api_data_staging';
        } else {
            $dbTable = $table;
        }

        $allLocalesSlugs = [];
        $allLocalesArray = isset($courseApiData['allLocales']) ? unserialize($courseApiData['allLocales']) : null;
        if (is_array($allLocalesArray) && count($allLocalesArray) > 0) {
            foreach ($allLocalesArray as $locale) {
                $langCode = $locale['LocaleCode'];
                $foundCodeSlug = $this->getSlugFromLocalCode($langCode);
                if ($foundCodeSlug) {
                    array_push($allLocalesSlugs, $foundCodeSlug);
                }
            }
        }

        $allSlugsDelimited = implode('|', $allLocalesSlugs);

        if ($this->isCourseApiRowExists($courseApiData['cldId'], $env, $dbTable)) {
            // Row Exists - Update
            $database->update(
                $dbTable,
                [
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
                    'cldImageUrl' => $courseApiData['cldImageUrl'],
                    'courseImageUrl' => $courseApiData['courseImageUrl'],
                    'courseImageThumbUrl' => $courseApiData['courseImageThumbUrl'],
                    'courseInformation' => $courseApiData['courseInformation'],
                    'marketingDescription' => $courseApiData['marketingDescription'],
                    'courseOutline' => $courseApiData['courseOutline'],
                    'courseObjectives' => $courseApiData['courseObjectives'],
                    'courseRegulations' => $courseApiData['courseRegulations'],
                ],
                ['cldId' => $courseApiData['cldId']]
            );
        } else {
            // Row Doesn't Exist Insert
            $database->insert($dbTable, [
                'title' => $courseApiData['title'],
                'cldId' => $courseApiData['cldId'],
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
                'cldImageUrl' => $courseApiData['cldImageUrl'],
                'courseImageUrl' => $courseApiData['courseImageUrl'],
                'courseImageThumbUrl' => $courseApiData['courseImageThumbUrl'],
                'courseInformation' => $courseApiData['courseInformation'],
                'marketingDescription' => $courseApiData['marketingDescription'],
                'courseOutline' => $courseApiData['courseOutline'],
                'courseObjectives' => $courseApiData['courseObjectives'],
                'courseRegulations' => $courseApiData['courseRegulations'],
            ]);
        }
    }

    public function apiClidIdsStatic()
    {
        // Sept 6 2024
        $cldids = [
            '17952',
        ];

        return $cldids;
    }

    public function generateAddUpdateCldApiListToDb()
    {
        $token = $this->getBearerToken();

        $data = $this->getLastUpdatedList($token);
        // header('Content-Type: application/json; Charset=UTF-8');

        foreach ($data as $course) {
            $CatalogId = $course['CatalogId'];
            $LessonName = $course['LessonName'];
            $LastUpdate = ! empty($course['LastUpdate']) ? date('Y-m-d H:i:s', strtotime($course['LastUpdate'])) : null;
            $oneMonthAgo = new \DateTime('1 month ago');
            $lastMonthDate = $oneMonthAgo->format('Y-m-d H:i:s');
            if ($LastUpdate > $lastMonthDate) {
                echo '<pre>';
                print_r($course);
                echo '</pre>';
                // echo $CatalogId . '<br>';
                // echo $LessonName . '<br>';
                // echo 'Last Update: ' . $LastUpdate . '<br>';
                // echo 'Last Month Date: ' . $lastMonthDate . '<br>';
                // echo '---------<br>';
                // array_push($cldids, str_replace("CLD-", "", $CatalogId));
            }
        }
    }

    public function checkIfCourseExtraDataExists($cldid)
    {
        $database = parent::db();

        $count = $database->count('course_api_append_data', [
            'cldId' => $cldid,
        ]);

        $exists = ($count > 0) ? true : false;

        return $exists;
    }

    public function checkIfCldIdToRemoveHasAlreadyBeenRemoved($cldId)
    {
        $database = parent::db();

        $count = $database->count('course_api_to_delete_backup', [
            'cldid' => $cldId,
        ]);

        $exists = ($count > 0) ? true : false;

        return $exists;
    }

    public function apiClidIdsToRemove()
    {
        $database = parent::db();
        $token = $this->getBearerToken();
        $data = $this->getMarketingCoursehsInactive($token);
        $cldids = [];
        $count = 1;
        foreach ($data as $course) {
            if (! $this->checkIfCldIdToRemoveHasAlreadyBeenRemoved($course['CatalogId'])) {
                // echo '<pre>';
                // print_r($course);
                // echo '</pre>';
                // $CatalogId = $course['CatalogId'];
                // $LessonName = $course['LessonName'];
                // $LastUpdate = date('Y-m-d H:i:s', strtotime($course['LastUpdate']));
                // $SunsetStartDate = $course['SunsetStartDate'];
                // $SunsetEndDate = $course['SunsetEndDate'];
                // $oneMonthAgo = new \DateTime('1 month ago');
                // $lastMonthDate = $oneMonthAgo->format('Y-m-d H:i:s');
                // if (!empty($SunsetStartDate) || !empty($SunsetEndDate)) {
                // echo json_encode($course);
                echo 'CatalogId: '.$course['CatalogId'].PHP_EOL;
                $database->insert('course_api_to_delete', [
                    'cldid' => $course['CatalogId'],
                    'title' => $course['LessonName'],
                ]);
                $count++;
                // }
            }
        }
    }

    public function backUpCldidsToRemove()
    {
        $database = parent::db();

        $data = $database->select('course_api_to_delete', '*');
        foreach ($data as $courseApiData) {
            if ($this->isCourseToDeleteBackupApiRowExists($courseApiData['cldid'])) {
                // Row Exists - Update
                $database->update(
                    'course_api_to_delete_backup',
                    [
                        'title' => $courseApiData['title'],
                        'cldid' => $courseApiData['cldid'],
                        'date_backed_up' => date('Y-m-d H:i:s'),
                    ],
                    ['cldid' => $courseApiData['cldid']]
                );
            } else {
                // Row Doesn't Exist Insert
                $database->insert('course_api_to_delete_backup', [
                    'title' => $courseApiData['title'],
                    'cldid' => $courseApiData['cldid'],
                    'date_backed_up' => date('Y-m-d H:i:s'),
                ]);
            }
            $database->delete('course_api_to_delete', [
                'AND' => [
                    'cldid' => $courseApiData['cldid'],
                ],
            ]);
        }
    }

    public function entriesToDisableFeedCron()
    {
        $this->backUpCldidsToRemove();
        $this->apiClidIdsToRemove();
        $feedMeUrl = 'https://hsi.com/index.php?p=actions/feed-me/feeds/run-task&direct=1&feedId=74&passkey=jsmfojjkpe';
        $this->triggerFeedMeAction($feedMeUrl);
    }

    public function isImageUpdatedWithinMonth($LessonID, $token)
    {
        $url = $this->apiEnpointBaseUrl.'/api/LessonThumbnail/'.$LessonID;
        $thumbnailData = $this->doCurlGetRequest($url, $token);

        // Check if response is valid and is an array with data
        if (empty($thumbnailData) || ! is_array($thumbnailData) || ! isset($thumbnailData[0])) {
            return false;
        }

        // Check if UploadDate field exists
        if (! isset($thumbnailData[0]['UploadDate']) || empty($thumbnailData[0]['UploadDate'])) {
            return false;
        }

        $uploadDate = date('Y-m-d H:i:s', strtotime($thumbnailData[0]['UploadDate']));
        $oneMonthAgo = new \DateTime('1 month ago');
        $lastMonthDate = $oneMonthAgo->format('Y-m-d H:i:s');

        if ($uploadDate > $lastMonthDate) {
            return true;
        }

        return false;
    }

    private function isWithinMonth($course)
    {
        if (! isset($course['LastUpdate']) || empty($course['LastUpdate'])) {
            return false;
        }

        $LastUpdate = date('Y-m-d H:i:s', strtotime($course['LastUpdate']));
        $oneMonthAgo = new \DateTime('1 month ago');
        $lastMonthDate = $oneMonthAgo->format('Y-m-d H:i:s');

        if ($LastUpdate > $lastMonthDate) {
            return true;
        }

        return false;
    }

    public function isEnglishLocalUpdatedWithinMonth($LessonId, $token)
    {
        $cldData = $this->getCldDataRequest($token, $LessonId);
        $course = $cldData[0];
        $isUpdated = false;
        if (isset($course['Locales'])) {
            foreach ($course['Locales'] as $locale) {
                if ($locale['LocaleCode'] == 'en_US') {
                    if (! empty($locale['LastUpdateDate'])) {
                        $lastUpdateDate = date('Y-m-d H:i:s', strtotime($locale['LastUpdateDate']));
                        $lastUpdateDateAgo = new \DateTime('1 month ago');
                        $lastMonthDate = $lastUpdateDateAgo->format('Y-m-d H:i:s');

                        if ($lastUpdateDate > $lastMonthDate) {
                            $isUpdated = true;
                        }
                    }
                    break;
                }
            }
        }

        return $isUpdated;
    }

    public function apiClidIds($printData = false)
    {
        $token = $this->getBearerToken();

        $data = $this->getLastUpdatedList($token);
        if (! $token || empty($data)) {
            echo 'no cldids retrieved';
            exit();
        }

        echo 'Total Courses: '.count($data).PHP_EOL;

        $cldids = [];
        foreach ($data as $course) {
            $LessonId = $course['LessonId'];
            $LessonIdWithinMonth = $this->isWithinMonth($course);
            $imageUpdatedWithinMonth = $this->isImageUpdatedWithinMonth($LessonId, $token);
            $englishLocalUpdatedWithinMonth = $this->isEnglishLocalUpdatedWithinMonth($LessonId, $token);

            if ($LessonIdWithinMonth || $imageUpdatedWithinMonth || $englishLocalUpdatedWithinMonth) {
                $LessonId = $course['LessonId'];
                $LessonName = $course['LessonName'];
                $LastUpdate = ! empty($course['LastUpdate']) ? date('Y-m-d H:i:s', strtotime($course['LastUpdate'])) : null;
                $oneMonthAgo = new \DateTime('1 month ago');
                $lastMonthDate = $oneMonthAgo->format('Y-m-d H:i:s');

                if (! $printData) {
                    echo $LessonId.PHP_EOL;
                    echo $LessonName.PHP_EOL;
                    echo 'Course Updated Within 1 month: '.($LessonIdWithinMonth ? 'Yes' : 'No').PHP_EOL;
                    echo 'English Locale Updated Within 1 month: '.($englishLocalUpdatedWithinMonth ? 'Yes' : 'No').PHP_EOL;
                    echo 'Image Updated Within 1 month: '.($imageUpdatedWithinMonth ? 'Yes' : 'No').PHP_EOL;
                    echo 'Last Update: '.$LastUpdate.PHP_EOL;
                    echo 'Last Month Date: '.$lastMonthDate.PHP_EOL;
                    echo '---------'.PHP_EOL;
                }
                if ($printData) {
                    // echo json_encode($course);
                    echo '<pre>';
                    print_r($course);
                    echo '</pre>';
                }
                array_push($cldids, $LessonId);
            } else {
                // echo $LessonId. ' not within month.'.PHP_EOL;
                // if(!$imageUpdatedWithinMonth){
                //     echo $LessonId. ' image not updates within the last month'.PHP_EOL;
                //     echo '----------'.PHP_EOL;
                // }
            }
        }

        // if ($printData) {
        //     header('Content-Type: application/json; Charset=UTF-8');
        //     echo json_encode($data);
        //     echo '<pre>';
        //     print_r($data);
        //     echo '</pre>';
        // }
        echo 'CLD-IDs Retrieved'.PHP_EOL;
        print_r($cldids);

        return $cldids;
    }

    public function generateAddUpdateCldApiDataFromStaticList()
    {
        // Oct 29 2024
        $cldids = [
            17970,
        ];

        foreach ($cldids as $cldid) {
            $coursData = $this->getCourseCldData($cldid);
            $this->insertUpdateCourseApiData($coursData);
        }
    }

    public function backUpPreviousFeedData($env = 'prod')
    {
        $database = parent::db();
        if ($env != 'prod') {
            $dbTable = 'course_api_data_staging';
        } else {
            $dbTable = 'course_api_data';
        }

        $data = $database->select($dbTable, '*');

        foreach ($data as $row) {
            // echo $row['title'] . '<br>';
            $capdid = $row['capdid'];
            $course = [
                'title' => $row['title'],
                'cldId' => $row['cldId'],
                'salesLibraryTopic' => $row['salesLibraryTopic'],
                'courseTopic' => $row['courseTopic'],
                'collections' => $row['collections'],
                'vendorId' => $row['vendorId'],
                'vendorName' => $row['vendorName'],
                'libraryId' => $row['libraryId'],
                'libraryName' => $row['libraryName'],
                'lessonId' => $row['lessonId'],
                'ej4CourseNumber' => $row['ej4CourseNumber'],
                'lessonModality' => $row['lessonModality'],
                'hsiProgramID' => $row['hsiProgramID'],
                'lessonLength' => $row['lessonLength'],
                'lessonAffiliations' => $row['lessonAffiliations'],
                'isRecommended' => $row['isRecommended'],
                'locale' => $row['locale'],
                'allLocales' => $row['allLocales'],
                'courseLanguageCategoriesSlug' => $row['courseLanguageCategoriesSlug'],
                'pricingTier' => $row['pricingTier'],
                'cldImageUrl' => $row['cldImageUrl'],
                'courseImageUrl' => $row['courseImageUrl'],
                'courseImageThumbUrl' => $row['courseImageThumbUrl'],
                'courseInformation' => $row['courseInformation'],
                'marketingDescription' => $row['marketingDescription'],
                'courseOutline' => $row['courseOutline'],
                'courseObjectives' => $row['courseObjectives'],
                'courseRegulations' => $row['courseRegulations'],
                'parentCldid' => $row['parentCldid'],
            ];
            $this->insertBackupCourseApiData($course, $env);

            $database->delete($dbTable, [
                'AND' => [
                    'capdid' => $capdid,
                ],
            ]);

        }
    }

    public function appendManualCldidsFromList()
    {

        $cldids = $this->apiClidIdsStatic();
        foreach ($cldids as $cldid) {
            $coursData = $this->getCourseCldData($cldid);
            $this->insertUpdateCourseApiData($coursData);
        }

        $this->matchParentToChildLanguages();
    }

    public function cronJobGenerateAddUpdateCldApiDataFromListTestFeed()
    {
        // Test function to trigger FeedMe action for test feed (feedId=82)
        $feedMeUrl = 'https://hsi.com/index.php/actions/feed-me/feeds/run-task?direct=1&feedId=82&passkey=jlcnismqqn';
        $this->triggerFeedMeAction($feedMeUrl);

    }

    public function triggerFeedMeAction($feedMeUrl)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $feedMeUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for testing
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disable SSL host verification for testing
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        echo "FeedMe Test Feed Response:\n";
        echo 'HTTP Code: '.$httpCode."\n";
        echo 'Response: '.$response."\n";
        if ($error) {
            echo 'cURL Error: '.$error."\n";
        }

        return [
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
        ];
    }

    public function cronJobGenerateAddUpdateCldApiDataFromList($manualList = [], $env = 'prod', $feedId = 70, $doSingleBatch = false, $runFeedMe = true)
    {

        if (! $doSingleBatch) {
            $table = 'course_api_data';
            $cldids = $this->apiClidIds();
            if (empty($cldids)) {
                echo 'no cldids returned';
                exit();
            }
            $this->backUpPreviousFeedData($env);

            if (! empty($manualList)) {
                $cldids = array_merge($cldids, $manualList);
            }
        } else {
            $cldids = $manualList;
            $table = 'course_api_data_singles';
            $database = parent::db();
            $database->query("TRUNCATE TABLE {$table}");
        }
        // echo 'cldids found';
        // exit();

        print_r($cldids);
        // exit();
        foreach ($cldids as $cldid) {
            $coursData = $this->getCourseCldData($cldid);
            if ($coursData === null) {
                echo "Skipping cldid {$cldid}: no course data returned.".PHP_EOL;

                continue;
            }
            $this->insertUpdateCourseApiData($coursData, $env, $table);
        }

        $this->matchParentToChildLanguages($env);

        if ($runFeedMe) {
            $env = $env ?? 'production'; // or however you set this
            $url = 'https://hsi.com/index.php/actions/feed-me/feeds/run-task?direct=1&feedId='.$feedId.'&passkey=jsmfojjkpe';
            if ($env === 'staging') {
                $url = 'https://stage.hsi.com/index.php/actions/feed-me/feeds/run-task?direct=1&feedId=74&passkey=jsmfojjkpe';
            }

            $this->triggerFeedMeAction($url);
        }

    }

    public function getExtraFieldsCldIds()
    {
        $cldids = [];
        $database = parent::db();
        $data = $database->select('course_api_append_data', '*');

        foreach ($data as $row) {
            if (empty($row['pricingTier']) || $row['pricingTier'] == null) {
                array_push($cldids, str_replace('CLD-', '', $row['cldId']));
            }
        }

        return $cldids;
    }

    public function updateExtraFields()
    {

        $cldids = $this->getExtraFieldsCldIds();
        if (empty($cldids)) {
            echo 'no cldids returned';
            exit();
        }
        // print_r($cldids);
        foreach ($cldids as $cldid) {
            $coursData = $this->getCourseCldDataExtra($cldid);
            if ($coursData) {
                $this->insertUpdateCourseExtraApiData($coursData);
            } else {
                echo 'cldid '.$cldid.' did not have any api data'.PHP_EOL;
            }
        }

        echo 'extra data pruned: '.count($cldids);
    }

    public function generateAddUpdateCldApiDataFromList()
    {
        $cldids = $this->apiClidIds();
        $cldidslist = [];
        foreach ($cldids as $cldid) {
            array_push($cldidslist, $cldid);
            $coursData = $this->getCourseCldData($cldid);
            $this->insertUpdateCourseApiData($coursData);
        }
        print_r($cldidslist);
    }

    public function getTextLanguage($text, $default)
    {
        $supported_languages = [
            'en',
            'fr',
            'es',
        ];
        // German word list
        // from http://wortschatz.uni-leipzig.de/Papers/top100de.txt
        $wordList['de'] = [
            'der',
            'die',
            'und',
            'in',
            'den',
            'von',
            'zu',
            'das',
            'mit',
            'sich',
            'des',
            'auf',
            'für',
            'ist',
            'im',
            'dem',
            'nicht',
            'ein',
            'Die',
            'eine',
        ];
        // English word list
        // from http://en.wikipedia.org/wiki/Most_common_words_in_English
        $wordList['en'] = [
            'the',
            'be',
            'to',
            'of',
            'and',
            'a',
            'in',
            'that',
            'have',
            'I',
            'it',
            'for',
            'not',
            'on',
            'with',
            'he',
            'as',
            'you',
            'do',
            'at',
        ];
        // French word list
        // from https://1000mostcommonwords.com/1000-most-common-french-words/
        $wordList['fr'] = [
            'comme',
            'que',
            'tait',
            'pour',
            'sur',
            'sont',
            'avec',
            'tre',
            'un',
            'ce',
            'par',
            'mais',
            'que',
            'est',
            'il',
            'eu',
            'la',
            'et',
            'dans',
            'mot',
        ];

        // Spanish word list
        // from https://spanishforyourjob.com/commonwords/
        $wordList['es'] = [
            'que',
            'no',
            'a',
            'la',
            'el',
            'es',
            'y',
            'en',
            'lo',
            'un',
            'por',
            'qu',
            'si',
            'una',
            'los',
            'con',
            'para',
            'est',
            'eso',
            'las',
        ];
        // clean out the input string - note we don't have any non-ASCII
        // characters in the word lists... change this if it is not the
        // case in your language wordlists!
        $text = preg_replace('/[^A-Za-z]/', ' ', $text);
        // count the occurrences of the most frequent words
        foreach ($supported_languages as $language) {
            $counter[$language] = 0;
        }
        for ($i = 0; $i < 20; $i++) {
            foreach ($supported_languages as $language) {
                $counter[$language] = $counter[$language] +
                    // I believe this is way faster than fancy RegEx solutions
                    substr_count($text, ' '.$wordList[$language][$i].' ');

            }
        }
        // get max counter value
        // from http://stackoverflow.com/a/1461363
        $max = max($counter);
        $maxs = array_keys($counter, $max);
        // if there are two winners - fall back to default!
        if (count($maxs) == 1) {
            $winner = $maxs[0];
            $second = 0;
            // get runner-up (second place)
            foreach ($supported_languages as $language) {
                if ($language != $winner) {
                    if ($counter[$language] > $second) {
                        $second = $counter[$language];
                    }
                }
            }
            // apply arbitrary threshold of 10%
            if (($second / $max) < 0.1) {
                return $winner;
            }
        }

        return $default;
    }

    public function encodeSpecialCharacters($text)
    {
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }

    public function encodeSpecialCharactersOld($text, $locale)
    {
        return $text;
        // return html_entity_decode(htmlentities($text));
        $result = mb_convert_encoding($text, 'UTF-8', 'HTML-ENTITIES');
        // //$result = mb_detect_encoding($text . " ", "UTF-8,CP1252") == "UTF-8" ? iconv("UTF-8", "CP1252", $text) : $text;
        // if ($locale != 'en_US') {
        //     //$result = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        //     //
        //     $result = mb_detect_encoding($text . " ", "UTF-8,CP1252") == "UTF-8" ? iconv("UTF-8", "CP1252", $text) : $text;
        //     // $result = iconv('UTF-8', 'ASCII//TRANSLIT', $result);
        // } else {
        //     $result = mb_detect_encoding($text . " ", "UTF-8,CP1252") == "UTF-8" ? iconv("UTF-8", "CP1252", $text) : $text;
        //     //$result = $text;
        //     //$result = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        //     // $result = mb_convert_encoding($text, "UTF-8", "HTML-ENTITIES");
        // }
        $result = str_replace('', "'", $result);
        $result = str_replace('', '-', $result);
        $result = str_replace('', 'œu', $result);
        $result = str_replace('', '"', $result);

        return $result;
        // $result = html_entity_decode(mb_convert_encoding(stripslashes($text), "HTML-ENTITIES", 'UTF-8'));
        // return $this->getTextLanguage($text, 'en');
        // return iconv('UTF-8', 'utf-8//TRANSLIT', $text);
        // return html_entity_decode(htmlentities($text, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'ISO-8859-1');
        // return $result;
    }

    public function convertToTableFromCoursesApiDB()
    {
        $database = parent::db();
        $courses = [];
        $data = $database->select('course_api_data', '*');
        foreach ($data as $row) {
            // echo $row['title'] . '<br>';
            $course = [
                'title' => $row['title'],
                'cldId' => $row['cldId'],
                'salesLibraryTopic' => $this->encodeSpecialCharacters($row['salesLibraryTopic'], $row['locale']),
                'courseTopic' => $this->encodeSpecialCharacters($row['courseTopic'], $row['locale']),
                'collections' => $row['collections'],
                'vendorId' => $row['vendorId'],
                'vendorName' => $row['vendorName'],
                'libraryId' => $row['libraryId'],
                'libraryName' => $this->encodeSpecialCharacters($row['libraryName'], $row['locale']),
                'lessonId' => $row['lessonId'],
                'ej4CourseNumber' => $row['ej4CourseNumber'],
                'lessonModality' => $row['lessonModality'],
                'hsiProgramID' => $row['hsiProgramID'],
                'lessonLength' => $row['lessonLength'],
                'locale' => $row['locale'],
                'courseLanguageCategoriesSlug' => $row['courseLanguageCategoriesSlug'],
                'pricingTier' => $row['pricingTier'],
                'courseImageUrl' => $row['courseImageUrl'],
                'courseImageThumbUrl' => $row['courseImageThumbUrl'],
                'courseInformation' => $this->encodeSpecialCharacters($row['courseInformation'], $row['locale']),
                'marketingDescription' => $this->encodeSpecialCharacters($row['marketingDescription'], $row['locale']),
                'courseOutline' => $this->encodeSpecialCharacters($row['courseOutline'], $row['locale']),
                'courseObjectives' => $this->encodeSpecialCharacters($row['courseObjectives'], $row['locale']),
                'courseRegulations' => $this->encodeSpecialCharacters($row['courseRegulations'], $row['locale']),
            ];
            array_push($courses, $course);
        }
        echo '<style>table  {border-collapse: collapse;}
        td, th {padding: 6px; border: 1px solid rgba(0,0,0,0.1);}</style>';
        echo '<table>';
        echo '<tr>'.
            '<td>title</td>'.
            '<td>cldId</td>'.
            '<td>salesLibraryTopic</td>'.
            '<td>courseTopic</td>'.
            '<td>collections</td>'.
            '<td>vendorId</td>'.
            '<td>vendorName</td>'.
            '<td>libraryId</td>'.
            '<td>libraryName</td>'.
            '<td>lessonId</td>'.
            '<td>ej4CourseNumber</td>'.
            '<td>lessonModality</td>'.
            '<td>hsiProgramID</td>'.
            '<td>lessonLength</td>'.
            '<td>locale</td>'.
            '<td>courseLanguageCategoriesSlug</td>'.
            '<td>pricingTier</td>'.
            '<td>courseImageUrl</td>'.
            '<td>courseImageThumbUrl</td>'.
            '<td>courseInformation</td>'.
            '<td>marketingDescription</td>'.
            '<td>courseOutline</td>'.
            '<td>courseObjectives</td>'.
            '<td>courseRegulations</td>';
        echo '</tr>';
        foreach ($courses as $row) {
            echo '<tr>'.
                '<td>'.$row['title'].'</td>'.
                '<td>'.$row['cldId'].'</td>'.
                '<td>'.$this->encodeSpecialCharacters($row['salesLibraryTopic'], $row['locale']).'</td>'.
                '<td>'.$this->encodeSpecialCharacters($row['courseTopic'], $row['locale']).'</td>'.
                '<td>'.$row['collections'].'</td>'.
                '<td>'.$row['vendorId'].'</td>'.
                '<td>'.$row['vendorName'].'</td>'.
                '<td>'.$row['libraryId'].'</td>'.
                '<td>'.$this->encodeSpecialCharacters($row['libraryName'], $row['locale']).'</td>'.
                '<td>'.$row['lessonId'].'</td>'.
                '<td>'.$row['ej4CourseNumber'].'</td>'.
                '<td>'.$row['lessonModality'].'</td>'.
                '<td>'.$row['hsiProgramID'].'</td>'.
                '<td>'.$row['lessonLength'].'</td>'.
                '<td>'.$row['locale'].'</td>'.
                '<td>'.$row['courseLanguageCategoriesSlug'].'</td>'.
                '<td>'.$row['pricingTier'].'</td>'.
                '<td>'.$row['courseImageUrl'].'</td>'.
                '<td>'.$row['courseImageThumbUrl'].'</td>'.
                '<td>'.$this->encodeSpecialCharacters($row['courseInformation'], $row['locale']).'</td>'.
                '<td>'.$this->encodeSpecialCharacters($row['marketingDescription'], $row['locale']).'</td>'.
                '<td>'.$this->encodeSpecialCharacters($row['courseOutline'], $row['locale']).'</td>'.
                '<td>'.$this->encodeSpecialCharacters($row['courseObjectives'], $row['locale']).'</td>'.
                '<td>'.$this->encodeSpecialCharacters($row['courseRegulations'], $row['locale']).'</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    public function findParentEnglishCldid($parentTitle, $env = 'prod')
    {
        $database = $this->db();

        if ($env != 'prod') {
            $dbTable = 'course_api_data_staging';
        } else {
            $dbTable = 'course_api_data';
        }

        $data = $database->select(
            $dbTable,
            '*',
            ['title' => $parentTitle]
        );
        foreach ($data as $row) {
            return $row['cldId'];
        }

        return false;
    }

    public function updateApiCourseByCldid($cldId, $option, $value, $env = 'prod')
    {
        $database = parent::db();

        if ($env != 'prod') {
            $dbTable = 'course_api_data_staging';
        } else {
            $dbTable = 'course_api_data';
        }

        $database->update(
            $dbTable,
            [
                ''.$option.'' => $value,
            ],
            ['cldId' => $cldId]
        );
    }

    public function getCourseOptionByClidid($cldId, $option, $env = 'prod')
    {
        $database = $this->db();

        if ($env != 'prod') {
            $dbTable = 'course_api_data_staging';
        } else {
            $dbTable = 'course_api_data';
        }

        $data = $database->select(
            $dbTable,
            '*',
            ['cldId' => $cldId]
        );
        foreach ($data as $row) {
            return $row[$option];
        }
    }

    public function matchParentToChildLanguages($env = 'prod')
    {
        $database = parent::db();

        if ($env != 'prod') {
            $dbTable = 'course_api_data_staging';
        } else {
            $dbTable = 'course_api_data';
        }

        $data = $database->select($dbTable, '*');
        $languagesArray = parent::languagesApiArray();
        foreach ($data as $row) {
            $parentTitle = '';
            $childCldid = $row['cldId'];
            foreach ($languagesArray as $slug => $language) {
                if (strstr($row['title'], $language)) {
                    $parentTitle = str_replace($language, '', $row['title']);
                    echo 'isLangauge:'.PHP_EOL;
                    echo 'currentTitle:'.$row['title'].PHP_EOL;
                    echo 'parentTitleSearch:'.$parentTitle.PHP_EOL;
                    $parentCldid = $this->findParentEnglishCldid(trim($parentTitle), $env);
                    if ($parentCldid) {
                        echo 'childCldId: '.$childCldid.PHP_EOL;
                        echo 'foundParentCldid: '.$parentCldid.PHP_EOL;
                        $this->updateApiCourseByCldid($childCldid, 'parentCldid', $parentCldid, $env);
                        $currentParentLanguageSlugs = $this->getCourseOptionByClidid($parentCldid, 'courseLanguageCategoriesSlug', $env);
                        echo 'currentParentSlug: '.$currentParentLanguageSlugs.PHP_EOL;
                        // if (empty($currentParentLanguageSlugs) || $currentParentLanguageSlugs == '') {
                        //     $this->updateApiCourseByCldid($parentCldid, 'courseLanguageCategoriesSlug', 'english');
                        // }
                        $this->updateApiCourseByCldid($parentCldid, 'courseLanguageCategoriesSlug', $currentParentLanguageSlugs.'|'.$slug, $env);
                    }
                    echo '-------'.PHP_EOL;
                }
            }
        }
    }

    public function disableSafetyOverviewFromArray($colArray)
    {
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        foreach ($colArray as $row) {
            // $collectionsNoBracket = str_replace(["[", "]", '"'], "", $row['collections']);
            // $collections = explode(",", $collectionsNoBracket);
            // $collections = implode("|", $collections);
            $course = [
                'cldid' => $row,
            ];
            array_push($courses, $course);
        }

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Avoid echo of empty string (which is invalid JSON), and
            // JSONify the error message instead:
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                // This should not happen, but we go all the way now:
                $json = '{"jsonError":"unknown"}';
            }
            // Set HTTP response status code to: 500 - Internal Server Error
            http_response_code(500);
        }
        echo $json;
    }

    public function generateNewSalesLibraryTopicFromArray($colArray)
    {
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        foreach ($colArray as $row) {
            // $collectionsNoBracket = str_replace(["[", "]", '"'], "", $row['collections']);
            // $collections = explode(",", $collectionsNoBracket);
            // $collections = implode("|", $collections);
            $course = [
                'cldid' => $row,
                'category' => 'Occupational Safety and Health',
            ];
            array_push($courses, $course);
        }

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Avoid echo of empty string (which is invalid JSON), and
            // JSONify the error message instead:
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                // This should not happen, but we go all the way now:
                $json = '{"jsonError":"unknown"}';
            }
            // Set HTTP response status code to: 500 - Internal Server Error
            http_response_code(500);
        }
        echo $json;
    }

    public function generateCollectionsFromArray($colArray)
    {
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        foreach ($colArray as $row) {
            $collectionsNoBracket = str_replace(['[', ']', '"'], '', $row['collections']);
            $collections = explode(',', $collectionsNoBracket);
            $collections = implode('|', $collections);
            $course = [
                'cldid' => $row['cldid'],
                'collections' => $collections,
            ];
            array_push($courses, $course);
        }

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Avoid echo of empty string (which is invalid JSON), and
            // JSONify the error message instead:
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                // This should not happen, but we go all the way now:
                $json = '{"jsonError":"unknown"}';
            }
            // Set HTTP response status code to: 500 - Internal Server Error
            http_response_code(500);
        }
        echo $json;
    }

    public function generateWorkPlaceViolenceCollections()
    {
        $database = parent::db();
        // header('Content-Type: application/json');
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        $data = $database->select('collections_wvc', '*');
        foreach ($data as $row) {
            $collectionsNoBracket = str_replace(['[', ']', '"'], '', $row['collections']);
            $collections = explode(',', $collectionsNoBracket);
            $collections = implode('|', $collections);
            $course = [
                'title' => $row['title'],
                'cldid' => $row['cldId'],
                'collections' => $collections,
            ];
            array_push($courses, $course);
        }

        // echo '<pre>';
        // print_r($courses);
        // echo '</pre>';
        // exit();

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Avoid echo of empty string (which is invalid JSON), and
            // JSONify the error message instead:
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                // This should not happen, but we go all the way now:
                $json = '{"jsonError":"unknown"}';
            }
            // Set HTTP response status code to: 500 - Internal Server Error
            http_response_code(500);
        }
        echo $json;
    }

    public function createFeedFromCoursesToDeleteApiDB()
    {
        $database = parent::db();
        // header('Content-Type: application/json');
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        $data = $database->select('course_api_to_delete', '*');
        foreach ($data as $row) {
            $course = [
                'title' => $row['title'],
                'cldid' => $row['cldid'],
            ];
            array_push($courses, $course);
        }

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Avoid echo of empty string (which is invalid JSON), and
            // JSONify the error message instead:
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                // This should not happen, but we go all the way now:
                $json = '{"jsonError":"unknown"}';
            }
            // Set HTTP response status code to: 500 - Internal Server Error
            http_response_code(500);
        }
        echo $json;
    }

    public function createFeedFromCoursesApiDBSpecificIds($db = 'prod')
    {
        $database = parent::db();
        // header('Content-Type: application/json');
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        $specficCourses = $this->apiClidIdsStatic();
        if ($db == 'staging') {
            $data = $database->select('course_api_data_staging', '*');
        } else {
            $data = $database->select('course_api_data', '*');
        }
        foreach ($data as $row) {
            $cldidOnly = str_replace('CLD-', '', $row['cldId']);
            if (in_array($cldidOnly, $specficCourses)) {
                if (empty($row['parentCldid'])) {
                    // echo $row['title'] . '<br>';
                    $currentLanguage = 'english';
                    if ($row['locale'] == 'fr_CA') {
                        $currentLanguage = 'french-canadian';
                    } elseif ($row['locale'] == 'es_US') {
                        $currentLanguage = 'french-canadian';
                    }

                    $affsString = '';
                    $affs = [];
                    if (isset($row['lessonAffiliations'])) {
                        $affsArr = unserialize($row['lessonAffiliations']);
                        foreach ($affsArr as $aff) {
                            array_push($affs, $aff['Description']);
                        }
                    }

                    $affsString = implode('|', $affs);

                    $course = [
                        'title' => $row['title'],
                        'cldId' => $row['cldId'],
                        'salesLibraryTopic' => $this->encodeSpecialCharacters($row['salesLibraryTopic'], $row['locale']),
                        'courseTopic' => $this->encodeSpecialCharacters($row['courseTopic'], $row['locale']),
                        'collections' => $row['collections'],
                        'vendorId' => $row['vendorId'],
                        'vendorName' => $row['vendorName'],
                        'libraryId' => $row['libraryId'],
                        'libraryName' => $this->encodeSpecialCharacters($row['libraryName'], $row['locale']),
                        'lessonId' => $row['lessonId'],
                        'ej4CourseNumber' => $row['ej4CourseNumber'],
                        'lessonModality' => $row['lessonModality'],
                        'hsiProgramID' => $row['hsiProgramID'],
                        'lessonLength' => $row['lessonLength'],
                        'locale' => $row['locale'],
                        'allLocales' => $row['allLocales'],
                        'lessonAffiliations' => $affsString,
                        'isRecommended' => ($row['isRecommended'] > 0) ? 'true' : 'false',
                        'courseLanguageCategoriesSlug' => $currentLanguage.$row['courseLanguageCategoriesSlug'],
                        'pricingTier' => $row['pricingTier'],
                        'courseImageUrl' => $row['courseImageUrl'],
                        'courseImageThumbUrl' => $row['courseImageThumbUrl'],
                        'courseInformation' => $this->encodeSpecialCharacters($row['courseInformation'], $row['locale']),
                        'marketingDescription' => $this->encodeSpecialCharacters($row['marketingDescription'], $row['locale']),
                        'courseOutline' => $this->encodeSpecialCharacters($row['courseOutline'], $row['locale']),
                        'courseObjectives' => $this->encodeSpecialCharacters($row['courseObjectives'], $row['locale']),
                        'courseRegulations' => $this->encodeSpecialCharacters($row['courseRegulations'], $row['locale']),
                    ];
                    array_push($courses, $course);
                }
            }
        }
        // echo json_encode($courses);
        // echo '<pre>';
        // print_r($courses);
        // echo '</pre>';
        // $current_charset = 'ISO-8859-15'; //or what it is now
        // array_walk_recursive($courses, function (&$value) use ($current_charset) {
        //     $value = iconv('UTF-8//TRANSLIT', $current_charset, $value);
        // });

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Avoid echo of empty string (which is invalid JSON), and
            // JSONify the error message instead:
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                // This should not happen, but we go all the way now:
                $json = '{"jsonError":"unknown"}';
            }
            // Set HTTP response status code to: 500 - Internal Server Error
            http_response_code(500);
        }
        echo $json;
    }

    public function createFeedForCourseCompletions()
    {
        $database = parent::db();
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        $data = $database->select('course_api_course_completions', '*');

        foreach ($data as $row) {

            $course = [
                'lesson_name' => $row['lesson_name'],
                'cld_id' => 'CLD-'.$row['cld_id'],
                'courseCompletions' => $row['courseCompletions'],
            ];
            array_push($courses, $course);
        }

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                $json = '{"jsonError":"unknown"}';
            }
            http_response_code(500);
        }
        echo $json;
    }

    public function createFeedForExtraFields()
    {
        $database = parent::db();
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        $data = $database->select('course_api_append_data', '*');

        foreach ($data as $row) {
            // Helper function to ensure UTF-8
            $ensureUtf8 = function ($string) {
                if (! is_string($string)) {
                    return $string;
                }
                // Check if the string is valid UTF-8
                if (mb_check_encoding($string, 'UTF-8')) {
                    return $string;
                }

                // Convert to UTF-8, replacing invalid characters
                return mb_convert_encoding($string, 'UTF-8', 'auto');
            };

            $affsString = '';
            $affs = [];
            if (isset($row['lessonAffiliation']) && $row['lessonAffiliation'] != 'a:0:{}') {
                $affsArr = unserialize($row['lessonAffiliation']);
                if (is_array($affsArr)) {
                    foreach ($affsArr as $aff) {
                        if (isset($aff['Description'])) {
                            array_push($affs, $ensureUtf8($aff['Description']));
                        }
                    }
                }
            }

            $affsString = implode('|', $affs);

            $ensureUtf8 = function ($string, $field = 'unknown') {
                if (! is_string($string)) {
                    return $string;
                }
                if (mb_check_encoding($string, 'UTF-8')) {
                    return $string;
                }
                // Log before conversion
                error_log("Converting field '$field': ".bin2hex($string));
                $result = mb_convert_encoding($string, 'UTF-8', 'auto');
                if ($result === false) {
                    error_log("Failed to convert field '$field': ".bin2hex($string));
                }

                return $result;
            };

            $course = [
                'title' => $ensureUtf8($row['title']),
                'cldId' => $ensureUtf8($row['cldId']),
                'allLocales' => (! empty($row['languages'])) ? $ensureUtf8($row['languages'], 'languages') : 'english',
                'lessonAffiliations' => $affsString,
                'isRecommended' => ($row['recommended'] > 0) ? 'true' : 'false',
                'pricingTier' => ! empty($row['pricingTier']) ? $ensureUtf8($row['pricingTier']) : '',
            ];
            array_push($courses, $course);
        }

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                $json = '{"jsonError":"unknown"}';
            }
            http_response_code(500);
        }
        echo $json;
    }

    public function createFeedFromCoursesApiDB($db = 'prod')
    {
        $database = parent::db();
        // header('Content-Type: application/json');
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        if ($db == 'staging') {
            $data = $database->select('course_api_data_staging', '*');
        } else {
            $data = $database->select('course_api_data', '*');
        }
        foreach ($data as $row) {
            if (empty($row['parentCldid'])) {
                // echo $row['title'] . '<br>';
                $currentLanguage = 'english';
                if ($row['locale'] == 'fr_CA') {
                    $currentLanguage = 'french-canadian';
                } elseif ($row['locale'] == 'es_US') {
                    $currentLanguage = 'french-canadian';
                }

                $affsString = '';
                $affs = [];
                if (isset($row['lessonAffiliations'])) {
                    $affsArr = unserialize($row['lessonAffiliations']);
                    foreach ($affsArr as $aff) {
                        array_push($affs, $aff['Description']);
                    }
                }

                $affsString = implode('|', $affs);

                $course = [
                    'title' => $row['title'],
                    'cldId' => $row['cldId'],
                    'salesLibraryTopic' => $this->encodeSpecialCharacters($row['salesLibraryTopic'], $row['locale']),
                    'courseTopic' => $this->encodeSpecialCharacters($row['courseTopic'], $row['locale']),
                    'collections' => $row['collections'],
                    'vendorId' => $row['vendorId'],
                    'vendorName' => $row['vendorName'],
                    'libraryId' => $row['libraryId'],
                    'libraryName' => $this->encodeSpecialCharacters($row['libraryName'], $row['locale']),
                    'lessonId' => $row['lessonId'],
                    'ej4CourseNumber' => $row['ej4CourseNumber'],
                    'lessonModality' => $row['lessonModality'],
                    'hsiProgramID' => $row['hsiProgramID'],
                    'lessonLength' => $row['lessonLength'],
                    'locale' => $row['locale'],
                    'allLocales' => $row['allLocales'],
                    'lessonAffiliations' => $affsString,
                    'isRecommended' => ($row['isRecommended'] > 0) ? 'true' : 'false',
                    'courseLanguageCategoriesSlug' => $currentLanguage.$row['courseLanguageCategoriesSlug'],
                    'pricingTier' => $row['pricingTier'],
                    'courseImageUrl' => $row['courseImageUrl'],
                    'courseImageThumbUrl' => $row['courseImageThumbUrl'],
                    'courseInformation' => $this->encodeSpecialCharacters($row['courseInformation'], $row['locale']),
                    'marketingDescription' => $this->encodeSpecialCharacters($row['marketingDescription'], $row['locale']),
                    'courseOutline' => $this->encodeSpecialCharacters($row['courseOutline'], $row['locale']),
                    'courseObjectives' => $this->encodeSpecialCharacters($row['courseObjectives'], $row['locale']),
                    'courseRegulations' => $this->encodeSpecialCharacters($row['courseRegulations'], $row['locale']),
                ];
                array_push($courses, $course);
            }
        }
        // echo json_encode($courses);
        // echo '<pre>';
        // print_r($courses);
        // echo '</pre>';
        // $current_charset = 'ISO-8859-15'; //or what it is now
        // array_walk_recursive($courses, function (&$value) use ($current_charset) {
        //     $value = iconv('UTF-8//TRANSLIT', $current_charset, $value);
        // });

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                $json = '{"jsonError":"unknown"}';
            }
            http_response_code(500);
        }
        echo $json;
    }

    /**
     * JSON feed from course_api_data_singles for Feed Me (singles / specific IDs).
     * Same structure as createFeedFromCoursesApiDB so Feed Me can parse it.
     */
    public function createFeedFromCoursesApiDBSingles()
    {
        $database = parent::db();
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        $data = $database->select('course_api_data_singles', '*');
        foreach ($data as $row) {
            if (isset($row['parentCldid']) && $row['parentCldid'] !== null && $row['parentCldid'] !== '') {
                continue;
            }
            $currentLanguage = 'english';
            if (isset($row['locale']) && $row['locale'] == 'fr_CA') {
                $currentLanguage = 'french-canadian';
            } elseif (isset($row['locale']) && $row['locale'] == 'es_US') {
                $currentLanguage = 'spanish';
            }
            $affsString = '';
            if (! empty($row['lessonAffiliations'])) {
                $affsArr = @unserialize($row['lessonAffiliations']);
                if (is_array($affsArr)) {
                    $affs = [];
                    foreach ($affsArr as $aff) {
                        if (isset($aff['Description'])) {
                            $affs[] = $aff['Description'];
                        }
                    }
                    $affsString = implode('|', $affs);
                }
            }
            $course = [
                'title' => $row['title'] ?? '',
                'cldId' => $row['cldId'] ?? '',
                'salesLibraryTopic' => $this->encodeSpecialCharacters($row['salesLibraryTopic'] ?? '', $row['locale'] ?? ''),
                'courseTopic' => $this->encodeSpecialCharacters($row['courseTopic'] ?? '', $row['locale'] ?? ''),
                'collections' => $row['collections'] ?? '',
                'vendorId' => $row['vendorId'] ?? '',
                'vendorName' => $row['vendorName'] ?? '',
                'libraryId' => $row['libraryId'] ?? '',
                'libraryName' => $this->encodeSpecialCharacters($row['libraryName'] ?? '', $row['locale'] ?? ''),
                'lessonId' => $row['lessonId'] ?? '',
                'ej4CourseNumber' => $row['ej4CourseNumber'] ?? '',
                'lessonModality' => $row['lessonModality'] ?? '',
                'hsiProgramID' => $row['hsiProgramID'] ?? '',
                'lessonLength' => $row['lessonLength'] ?? '',
                'locale' => $row['locale'] ?? '',
                'allLocales' => $row['allLocales'] ?? '',
                'lessonAffiliations' => $affsString,
                'isRecommended' => (! empty($row['isRecommended'])) ? 'true' : 'false',
                'courseLanguageCategoriesSlug' => $currentLanguage.($row['courseLanguageCategoriesSlug'] ?? ''),
                'pricingTier' => $row['pricingTier'] ?? '',
                'courseImageUrl' => $row['courseImageUrl'] ?? '',
                'courseImageThumbUrl' => $row['courseImageThumbUrl'] ?? '',
                'courseInformation' => $this->encodeSpecialCharacters($row['courseInformation'] ?? '', $row['locale'] ?? ''),
                'marketingDescription' => $this->encodeSpecialCharacters($row['marketingDescription'] ?? '', $row['locale'] ?? ''),
                'courseOutline' => $this->encodeSpecialCharacters($row['courseOutline'] ?? '', $row['locale'] ?? ''),
                'courseObjectives' => $this->encodeSpecialCharacters($row['courseObjectives'] ?? '', $row['locale'] ?? ''),
                'courseRegulations' => $this->encodeSpecialCharacters($row['courseRegulations'] ?? '', $row['locale'] ?? ''),
            ];
            $courses[] = $course;
        }
        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                $json = '{"jsonError":"unknown"}';
            }
            http_response_code(500);
        }
        echo $json;
    }

    public function createFeedFromCoursesApiDBWithLimitOffset($limit, $offset)
    {
        $database = parent::db();
        // header('Content-Type: application/json');
        header('Content-Type: application/json; Charset=UTF-8');
        $courses = [];
        if (! $offset) {
            $offset = 0;
        }
        $data = $database->select('course_api_data', '*', [
            'LIMIT' => [$offset, $limit],
        ]);
        foreach ($data as $row) {
            if (empty($row['parentCldid'])) {
                // echo $row['title'] . '<br>';
                $currentLanguage = 'english';
                if ($row['locale'] == 'fr_CA') {
                    $currentLanguage = 'french-canadian';
                } elseif ($row['locale'] == 'es_US') {
                    $currentLanguage = 'french-canadian';
                }
                $course = [
                    'title' => $row['title'],
                    'cldId' => $row['cldId'],
                    'salesLibraryTopic' => $this->encodeSpecialCharacters($row['salesLibraryTopic'], $row['locale']),
                    'courseTopic' => $this->encodeSpecialCharacters($row['courseTopic'], $row['locale']),
                    'collections' => $row['collections'],
                    'vendorId' => $row['vendorId'],
                    'vendorName' => $row['vendorName'],
                    'libraryId' => $row['libraryId'],
                    'libraryName' => $this->encodeSpecialCharacters($row['libraryName'], $row['locale']),
                    'lessonId' => $row['lessonId'],
                    'ej4CourseNumber' => $row['ej4CourseNumber'],
                    'lessonModality' => $row['lessonModality'],
                    'hsiProgramID' => $row['hsiProgramID'],
                    'lessonLength' => $row['lessonLength'],
                    'locale' => $row['locale'],
                    'courseLanguageCategoriesSlug' => $currentLanguage.$row['courseLanguageCategoriesSlug'],
                    'pricingTier' => $row['pricingTier'],
                    'courseImageUrl' => $row['courseImageUrl'],
                    'courseImageThumbUrl' => $row['courseImageThumbUrl'],
                    'courseInformation' => $this->encodeSpecialCharacters($row['courseInformation'], $row['locale']),
                    'marketingDescription' => $this->encodeSpecialCharacters($row['marketingDescription'], $row['locale']),
                    'courseOutline' => $this->encodeSpecialCharacters($row['courseOutline'], $row['locale']),
                    'courseObjectives' => $this->encodeSpecialCharacters($row['courseObjectives'], $row['locale']),
                    'courseRegulations' => $this->encodeSpecialCharacters($row['courseRegulations'], $row['locale']),
                ];
                array_push($courses, $course);
            }
        }

        $json = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Avoid echo of empty string (which is invalid JSON), and
            // JSONify the error message instead:
            $json = json_encode(['jsonError' => json_last_error_msg()]);
            if ($json === false) {
                // This should not happen, but we go all the way now:
                $json = '{"jsonError":"unknown"}';
            }
            // Set HTTP response status code to: 500 - Internal Server Error
            http_response_code(500);
        }
        echo $json;
    }

    public function createFeedFromCoursesApiDBXml()
    {
        $database = parent::db();
        header('Content-Type: application/xml; charset=utf-8');
        $xmlString = '';
        $xmlString .= '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xmlString .= '<root>';
        $xmlString .= ' <courses>';
        $data = $database->select('course_api_data', '*');
        foreach ($data as $row) {
            $xmlString .= '<course>';
            $xmlString .= '<title>
                <![CDATA['.$row['title'].']]>
            </title>
            <cldId>'.$row['cldId'].'</cldId>
            <salesLibraryTopic>
                <![CDATA['.$row['salesLibraryTopic'].']]>
            </salesLibraryTopic>
            <courseTopic>
                <![CDATA['.$row['courseTopic'].']]>
            </courseTopic>
            <Collections>'.$row['collections'].'</Collections>
            <vendorId>'.$row['vendorId'].'</vendorId>
            <vendorName>'.$row['vendorName'].'</vendorName>
            <libraryId>'.$row['libraryId'].'</libraryId>
            <libraryName>
                <![CDATA['.$row['libraryName'].']]>
            </libraryName>
            <lessonId>'.$row['lessonId'].'</lessonId>
            <ej4CourseNumber>'.$row['ej4CourseNumber'].'</ej4CourseNumber>
            <lessonModality>'.$row['lessonModality'].'</lessonModality>
            <hsiProgramID>'.$row['hsiProgramID'].'</hsiProgramID>
            <lessonLength>'.$row['lessonLength'].'</lessonLength>
            <locale>'.$row['locale'].'</locale>
            <courseLanguageCategoriesSlug>'.$row['courseLanguageCategoriesSlug'].'</courseLanguageCategoriesSlug>
            <PricingTier>'.$row['pricingTier'].'</PricingTier>
            <courseImageUrl>'.$row['courseImageUrl'].'</courseImageUrl>
            <courseImageThumbUrl>'.$row['courseImageThumbUrl'].'</courseImageThumbUrl>
            <courseInformation>
                <![CDATA['.$row['courseInformation'].']]>
            </courseInformation>
            <marketingDescription>
                <![CDATA['.$row['marketingDescription'].']]>
            </marketingDescription>
            <courseOutline>
                <![CDATA['.$row['courseOutline'].']]>
            </courseOutline>
            <courseObjectives>
                <![CDATA['.$row['courseObjectives'].']]>
            </courseObjectives>
            <courseRegulations>
                <![CDATA['.$row['courseRegulations'].']]>
            </courseRegulations>
        </course>';
        }
        $xmlString .= '</courses>';
        $xmlString .= '</root>';
        $xmlString = trim($xmlString);
        echo $xmlString;
        // $dom = new DOMDocument;
        // $dom->preserveWhiteSpace = FALSE;
        // $dom->loadXML($xmlString);
        // $dom->save(dirname(dirname(__FILE__)) . '/xml/coursesApiFeed.xml');
    }
}
$cldapi = new CldApi;
