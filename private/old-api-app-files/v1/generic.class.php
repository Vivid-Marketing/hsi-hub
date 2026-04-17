<?php

// $http_origin = $_SERVER['HTTP_ORIGIN'];

// if ($http_origin == "https://aws.hsi.test" || $http_origin == "https://standbyhsi.com" || $http_origin == "https://hsi.com") {
//     header("Access-Control-Allow-Origin: $http_origin");
// }
error_reporting(E_ALL);
ini_set('display_errors', 'On');
include dirname(dirname(__FILE__)).'/vendor/autoload.php';

// use GuzzleHttp\Client;
// use GuzzleHttp\Request;
use Aws\S3\S3Client;
// use Spatie\ImageOptimizer\OptimizerChainFactory;
// use Spatie\Image\Image;
use Medoo\Medoo;

// require_once 'meekrodb.2.4.class.php';

class Generic
{
    public $cldidsUsed;

    public function __construct()
    {
        define('ROOTDIR_NOSLASH', dirname(dirname(__FILE__)));
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(dirname(__FILE__)));
        $dotenv->load();
        $this->cldidsUsed = [];
    }

    public function db()
    {
        $database = new Medoo([
            'type' => 'mysql',
            'host' => '127.0.0.1',
            'database' => $_ENV['DBNAME'],
            'username' => $_ENV['DBUSER'],
            'password' => $_ENV['DBPASS'],
        ]);

        return $database;
    }

    public function helloTest()
    {
        $db = $this->db();
        echo $_ENV['ENVIRONMENT'].'<br/> '.ROOTDIR_NOSLASH;
    }

    /**
     * Ensure directory exists and is writable
     * Creates directory recursively if it doesn't exist
     *
     * @param  string  $dirPath  Full path to directory
     * @param  int  $permissions  Directory permissions (default: 0755)
     * @return bool True if directory exists and is writable, false otherwise
     */
    public function ensureDirectoryExists($dirPath, $permissions = 0755)
    {
        if (empty($dirPath)) {
            return false;
        }

        // Normalize path
        $dirPath = rtrim($dirPath, '/');

        // Check if directory already exists
        if (is_dir($dirPath)) {
            // Check if it's writable
            if (is_writable($dirPath)) {
                return true;
            } else {
                // Try to make it writable
                @chmod($dirPath, $permissions);

                return is_writable($dirPath);
            }
        }

        // Directory doesn't exist, create it recursively
        if (! @mkdir($dirPath, $permissions, true)) {
            error_log("Failed to create directory: {$dirPath}");

            return false;
        }

        // Verify it was created and is writable
        if (is_dir($dirPath) && is_writable($dirPath)) {
            return true;
        }

        error_log("Directory created but not writable: {$dirPath}");

        return false;
    }

    public function languagesArray()
    {
        $languagesArray = [
            'french-canadian' => '- French Canadian',
            'spanish' => '- Spanish',
        ];

        return $languagesArray;
    }

    public function languagesApiArray()
    {
        $languagesArray = [
            'french-canadian' => 'French Canadian -',
            'spanish' => 'Spanish -',
        ];

        return $languagesArray;
    }

    public function uploadToDoSpaces($pathToFile, $filename, $type = 'image/jpeg')
    {
        // settings
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'sfo2',
            'endpoint' => 'https://sfo2.digitaloceanspaces.com',
            'use_path_style_endpoint' => false,
            'credentials' => [
                'key' => 'H7TJ5QSXXQNU2XIWNB4K',
                'secret' => 'l1m7ZDKF8RtJ0anygpJmgxoABVJz43jmO+40WTpIVRs',
            ],
        ]);

        // upload the image to the server base64
        $result = $client->putObject([
            'Bucket' => 'hsiassetstorage',
            'Key' => $filename,
            'SourceFile' => $pathToFile,
            'ACL' => 'public-read',
            'ContentType' => $type,
            'ContentEncoding' => 'base64',
        ]);

        $res = $result->toArray();

        // echo '<pre>';
        // print_r($result->toArray());
        // echo '</pre>';
        return $res['ObjectURL'];
    }

    public function uploadLargeAndSmallThumbsToDigitalOcean()
    {
        $database = $this->db();
        $data = $database->select('optimized_course_images', '*');
        foreach ($data as $row) {
            $filenameLG = basename($row['optUrl']);
            $filenameSM = basename($row['smUrl']);
            $lgCdnUrl = $this->uploadToDoSpaces($row['optUrl'], $filenameLG);
            $smCdnUrl = $this->uploadToDoSpaces($row['smUrl'], $filenameSM);
            $database->update(
                'optimized_course_images',
                [
                    'doLgUrl' => $lgCdnUrl,
                    'doSmUrl' => $smCdnUrl,
                ],
                ['cldiid' => $row['cldiid']]
            );
            echo 'Uploded LG and SM Images to DO Spaces: Large - '.$row['optUrl'].'and Small - '.$row['smUrl'].PHP_EOL;
        }
    }

    public function file_url($url)
    {
        $parts = parse_url($url);
        $path_parts = array_map('rawurldecode', explode('/', $parts['path']));

        return
            $parts['scheme'].'://'.
            $parts['host'].
            implode('/', array_map('rawurlencode', $path_parts));
    }

    public function uploadImageFile($file)
    {
        $target_dir = dirname(dirname(__FILE__)).'/uploads/single-images/';
        $new_file_name = str_replace(' ', '_', $file['imageFile']['name']);
        $target_file = $target_dir.$new_file_name;
        $uploadOk = 0;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        // Check if image file is a actual image or fake image

        if (move_uploaded_file($file['imageFile']['tmp_name'], $target_file)) {
            echo '<div class="alert alert-success" role="alert">The file '.htmlspecialchars(basename($file['imageFile']['name'])).' has been uploaded.</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">Sorry, there was an error uploading your file.</div>';
        }

        $filename = $new_file_name;
        $filenameUrl = 'https://apismd.hsi.com/uploads/single-images/'.$filename;
        // echo $filenameUrl . '<br>';
        // Large Image
        $pathToOutputLg = dirname(dirname(__FILE__)).'/uploads/single-images/optimized/lg-'.$filename;
        // echo $pathToOutputLg . '<br>';
        $this->generate_image_thumbnail($filenameUrl, $pathToOutputLg, 976, 549);
        // Small Image
        $pathToOutputSm = dirname(dirname(__FILE__)).'/uploads/single-images/optimized/small/sm-'.$filename;
        // echo $pathToOutputSm . '<br>';
        $this->generate_image_thumbnail($filenameUrl, $pathToOutputSm, 226, 127);

        $localUrlLg = dirname(dirname(__FILE__)).'/uploads/single-images/optimized/lg-'.$filename;
        $localUrlSm = dirname(dirname(__FILE__)).'/uploads//single-images/optimized/small/sm-'.$filename;
        $filenameLG = 'lg-'.$filename;
        $filenameSM = 'sm-'.$filename;
        $lgCdnUrlObject = $this->uploadToDoSpaces($localUrlLg, $filenameLG);
        $smCdnUrlObject = $this->uploadToDoSpaces($localUrlSm, $filenameSM);

        echo '<h2>New Large URL</h2>';
        echo '<pre>'.$lgCdnUrlObject.'</pre>';
        echo '<h2>New Small URL</h2>';
        echo '<pre>'.$smCdnUrlObject.'</pre>';

        // return $target_dir . htmlspecialchars(basename($file["imageFile"]["name"]));
    }

    public function uploadReportPdfToDigitalOcean($fileLocationOnServer, $filename)
    {
        $pdfCdnUrl = $this->uploadToDoSpaces($fileLocationOnServer, $filename, 'application/pdf');

        return $pdfCdnUrl;
    }

    public function uploadLargeThumbsToDigitalOcean()
    {
        $database = $this->db();
        $data = $database->select('optimized_course_images', '*');
        foreach ($data as $row) {
            $filename = basename($row['optUrl']);
            $lgCdnUrl = $this->uploadToDoSpaces($row['optUrl'], $filename);
            $database->update(
                'optimized_course_images',
                [
                    'doLgUrl' => $lgCdnUrl,
                ],
                ['cldiid' => $row['cldiid']]
            );
            echo 'Uploded LG Images to DO Spaces: '.$filename.PHP_EOL;
        }
    }

    public function getCourseOutlineHtml($cldid)
    {
        $count = 0;
        $outlineHtml = '<ul>';
        $database = $this->db();
        $data = $database->select('course_outline', '*', [
            'CatalogId' => $cldid,
        ]);
        foreach ($data as $row) {
            if ($row['sectionName'] == 'NULL' || empty($row['sectionName'])) {
                return false;
            }
            $outlineHtml .= '<li>'.$row['sectionName'].'</li>';
            $count++;
        }
        $outlineHtml .= '</ul>';
        if ($count < 1) {
            return '';
        }

        return $outlineHtml;
    }

    public function getCourseObjectivesHtml($cldid)
    {
        $count = 0;
        $objectiveHtml = '<ul>';
        $database = $this->db();
        $data = $database->select('objectives', '*', [
            'CatalogId' => $cldid,
        ]);
        foreach ($data as $row) {
            if ($row['objectiveText'] == 'NULL' || empty($row['objectiveText'])) {
                return false;
            }
            $objectiveHtml .= '<li>'.$row['objectiveText'].'</li>';
            $count++;
        }
        $objectiveHtml .= '</ul>';
        if ($count < 1) {
            return '';
        }

        return $objectiveHtml;
    }

    public function getCourseRegsHtml($cldid)
    {
        $count = 0;
        $regHtml = '<ul>';
        $database = $this->db();
        $data = $database->select('regs', '*', [
            'CatalogId' => $cldid,
            'regreqItem[!]' => 'NULL',
        ]);
        foreach ($data as $row) {
            if ($row['regreqItem'] == 'NULL' || empty($row['regreqItem'])) {
                return false;
            }
            $regHtml .= '<li>'.$row['regreqItem'].'</li>';
            $count++;
        }
        $regHtml .= '</ul>';
        if ($count < 1) {
            return '';
        }

        return $regHtml;
    }

    public function getCourseDataCount()
    {
        $database = $this->db();

        $count = $database->count('course_data');

        return $count;
    }

    public function slugify($text, string $divider = '-')
    {
        // replace non letter or digits by divider
        $text = preg_replace('~[^\pL\d]+~u', $divider, $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, $divider);

        // remove duplicate divider
        $text = preg_replace('~-+~', $divider, $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    public function downloadImageReturnPath($srcUrl, $filename)
    {
        $target_dir = dirname(dirname(__FILE__)).'/uploads/course-images/';
        $localPath = $target_dir;
        $img = $localPath.$filename;
        if (! empty($srcUr)) {
            file_put_contents($img, file_get_contents($srcUrl));

            return $img;
        }
    }

    public function ifOptImageRowExists($cldid)
    {
        $database = $this->db();

        $count = $database->count('optimized_course_images', [
            'cldiid' => $cldid,
        ]);

        $exists = ($count > 0) ? true : false;

        return $exists;
    }

    public function updateInsertOptimizedSrcCourseImage($cldid, $srcUrl)
    {

        $database = $this->db();

        if ($this->ifOptImageRowExists($cldid)) {
            // Row Exists - Update
            $database->update(
                'optimized_course_images',
                [
                    'hgUrl' => $srcUrl,
                ],
                ['cldiid' => $cldid]
            );
        } else {
            // Row Doesn't Exist Insert
            $database->insert('optimized_course_images', [
                'cldiid' => $cldid,
                'hgUrl' => $srcUrl,
            ]);
        }
    }

    public function generate_image_thumbnail($source_image_path, $thumbnail_image_path, $width, $height)
    {

        $THUMBNAIL_IMAGE_MAX_WIDTH = $width;
        $THUMBNAIL_IMAGE_MAX_HEIGHT = $height;
        if (! getimagesize($source_image_path)) {
            return false;
        }

        // Ensure output directory exists before writing
        $outputDir = dirname($thumbnail_image_path);
        if (! $this->ensureDirectoryExists($outputDir)) {
            error_log("Failed to create or access directory: {$outputDir}");

            return false;
        }

        [$source_image_width, $source_image_height, $source_image_type] = getimagesize($source_image_path);
        switch ($source_image_type) {
            case IMAGETYPE_GIF:
                $source_gd_image = @imagecreatefromgif($source_image_path);
                break;
            case IMAGETYPE_JPEG:
                $source_gd_image = @imagecreatefromjpeg($source_image_path);
                break;
            case IMAGETYPE_PNG:
                $source_gd_image = @imagecreatefrompng($source_image_path);
                break;
        }
        if ($source_gd_image === false) {
            return false;
        }
        $source_aspect_ratio = $source_image_width / $source_image_height;
        $thumbnail_aspect_ratio = $THUMBNAIL_IMAGE_MAX_WIDTH / $THUMBNAIL_IMAGE_MAX_HEIGHT;
        if ($source_image_width <= $THUMBNAIL_IMAGE_MAX_WIDTH && $source_image_height <= $THUMBNAIL_IMAGE_MAX_HEIGHT) {
            $thumbnail_image_width = $source_image_width;
            $thumbnail_image_height = $source_image_height;
        } elseif ($thumbnail_aspect_ratio > $source_aspect_ratio) {
            $thumbnail_image_width = (int) ($THUMBNAIL_IMAGE_MAX_HEIGHT * $source_aspect_ratio);
            $thumbnail_image_height = $THUMBNAIL_IMAGE_MAX_HEIGHT;
        } else {
            $thumbnail_image_width = $THUMBNAIL_IMAGE_MAX_WIDTH;
            $thumbnail_image_height = (int) ($THUMBNAIL_IMAGE_MAX_WIDTH / $source_aspect_ratio);
        }
        $thumbnail_gd_image = imagecreatetruecolor($thumbnail_image_width, $thumbnail_image_height);
        imagecopyresampled($thumbnail_gd_image, $source_gd_image, 0, 0, 0, 0, $thumbnail_image_width, $thumbnail_image_height, $source_image_width, $source_image_height);

        // Attempt to write the image file
        if (! @imagejpeg($thumbnail_gd_image, $thumbnail_image_path, 90)) {
            error_log("Failed to write image file: {$thumbnail_image_path}");
            imagedestroy($source_gd_image);
            imagedestroy($thumbnail_gd_image);

            return false;
        }

        imagedestroy($source_gd_image);
        imagedestroy($thumbnail_gd_image);

        return true;
    }

    public function getLocalOptimizedThumb($cldid)
    {
        $database = $this->db();
        $data = $database->select(
            'optimized_course_images',
            '*',
            ['cldiid' => $cldid]
        );
        foreach ($data as $row) {
            $url = str_replace('/home/922328.cloudwaysapps.com/krxhrbsvzj/public_html/', 'https://apismd.hsi.com/', $row['optUrl']);

            return $url;
        }

        return '';
    }

    public function getDigitalOceanLgUrl($cldid)
    {
        $database = $this->db();
        $data = $database->select(
            'optimized_course_images',
            '*',
            ['cldiid' => $cldid]
        );
        foreach ($data as $row) {
            $url = $row['doLgUrl'];

            return $url;
        }

        return '';
    }

    public function getDigitalOceanSmUrl($cldid)
    {
        $database = $this->db();
        $data = $database->select(
            'optimized_course_images',
            '*',
            ['cldiid' => $cldid]
        );
        foreach ($data as $row) {
            $url = $row['doSmUrl'];

            return $url;
        }

        return '';
    }

    public function optimizeCourseImagesSmallThumb()
    {
        $database = $this->db();
        $data = $database->select('optimized_course_images', '*');
        foreach ($data as $row) {
            $filename = str_replace('lg-C', 'sm-C', basename($row['optUrl']));
            $pathToOutput = dirname(dirname(__FILE__)).'/uploads/course-images/optimized/small/'.$filename;
            if (! empty($row['optUrl'])) {
                $generatedThumb = $this->generate_image_thumbnail($row['optUrl'], $pathToOutput, 226, 127);
                if ($generatedThumb) {
                    $database->update(
                        'optimized_course_images',
                        [
                            'smUrl' => $pathToOutput,
                        ],
                        ['cldiid' => $row['cldiid']]
                    );
                    echo 'Optimized Small Image: '.$filename.PHP_EOL;
                } else {
                    echo 'Did Not Optimized Image: '.$row['cldiid'].PHP_EOL;
                }
            }
        }
    }

    public function optimizeCourseImages()
    {
        $database = $this->db();
        $data = $database->select('optimized_course_images', '*');
        foreach ($data as $row) {
            $filename = 'lg-'.basename($row['hgUrl']);
            $pathToOutput = dirname(dirname(__FILE__)).'/uploads/course-images/optimized/'.$filename;
            if (! empty($row['hgUrl'])) {
                $generatedThumb = $this->generate_image_thumbnail($row['hgUrl'], $pathToOutput, 976, 549);
                if ($generatedThumb) {
                    $database->update(
                        'optimized_course_images',
                        [
                            'optUrl' => $pathToOutput,
                        ],
                        ['cldiid' => $row['cldiid']]
                    );
                    echo 'Optimized Image: '.$filename.PHP_EOL;
                } else {
                    echo 'Did Not Optimized Image: '.$row['cldiid'].PHP_EOL;
                    echo 'URL: '.$row['hgUrl'].PHP_EOL;
                    echo '------'.PHP_EOL;
                }
            }
        }
    }

    public function downloadSrcToLocal()
    {
        $database = $this->db();
        // header('Content-Type: application/xml; charset=utf-8');
        $data = $database->select('course_data', '*');
        // $data = $database->select("course_data", "*", ["LIMIT" => [0, 10]]);
        foreach ($data as $row) {
            $newfileName = $row['CatalogID'].'-'.$this->slugify($row['lessonName']).'.jpeg';
            $target_dir = dirname(dirname(__FILE__)).'/uploads/course-images/';
            $localPath = $target_dir;
            $img = $localPath.$newfileName;
            if (! empty($row['Thumbnail'])) {
                echo $row['CatalogID'].PHP_EOL;
                file_put_contents($img, file_get_contents($row['Thumbnail']));
                $this->updateInsertOptimizedSrcCourseImage($row['CatalogID'], $img);
                echo 'Insert/Updated Download Local: '.$newfileName.PHP_EOL;
            }
        }
    }

    public function getLessonNameLanguageSlug($lessonName)
    {
        $languagesArray = $this->languagesArray();
        foreach ($languagesArray as $slug => $language) {
            if (strstr($lessonName, $language)) {
                return $slug;
            }
        }
    }

    public function getCourseLanguagesSlugs($cldid)
    {
        $languages = ['english'];
        $languagesArray = $this->languagesArray();
        $database = $this->db();
        $data = $database->select('course_data_languages', '*', [
            'parentCldid' => $cldid,
        ]);
        foreach ($data as $row) {
            foreach ($languagesArray as $slug => $language) {
                if (strstr($row['lessonName'], $language)) {
                    array_push($languages, $slug);
                }
            }
        }

        $languagesPiped = implode('|', $languages);

        return $languagesPiped;
    }

    public function buildXmlRow($row, $courseLanguagesSlugs)
    {
        $xmlString = '';

        $collections = [];
        $collection1 = $row['Collection1'];
        $collection2 = $row['Collection2'];
        $collection3 = $row['Collection3'];
        $collection4 = $row['Collection4'];
        if (! empty($collection1)) {
            array_push($collections, $collection1);
        }
        if (! empty($collection2)) {
            array_push($collections, $collection2);
        }
        if (! empty($collection3)) {
            array_push($collections, $collection3);
        }
        if (! empty($collection4)) {
            array_push($collections, $collection4);
        }
        $collectionGroup = implode('|', $collections);

        if ($row['SalesLibrary'] == 'Overviews') {
            $salesLibraryTopic = 'Safety Overviews';
        } else {
            $salesLibraryTopic = $row['SalesLibrary'];
        }

        $mktDescription = $row['mktDescription'];
        if (strstr($row['mktDescription'], 'Contact HSI for further information')) {
            $mktDescription = '';
        }

        $thumbnailLg = $this->getDigitalOceanLgUrl($row['CatalogID']);
        $thumbnailSm = $this->getDigitalOceanSmUrl($row['CatalogID']);

        $courseOutline = $this->getCourseOutlineHtml($row['CatalogID']) == false ? '' : '
<![CDATA['.$this->getCourseOutlineHtml($row['CatalogID']).']]>';
        $courseObjectives = $this->getCourseObjectivesHtml($row['CatalogID']) == false ? '' : '
<![CDATA['.$this->getCourseObjectivesHtml($row['CatalogID']).']]>';
        $courseRegs = $this->getCourseRegsHtml($row['CatalogID']) == false ? '' : '
<![CDATA['.$this->getCourseRegsHtml($row['CatalogID']).']]>';
        $xmlString .= '<course>';
        $xmlString .= '<title>
        <![CDATA['.$row['lessonName'].']]>
    </title>
    <cldId>'.$row['CatalogID'].'</cldId>
    <salesLibraryTopic>
        <![CDATA['.$salesLibraryTopic.']]>
    </salesLibraryTopic>
    <courseTopic>
        <![CDATA['.$row['Topic'].']]>
    </courseTopic>
    <Collections>'.$collectionGroup.'</Collections>
    <vendorId>'.$row['vendorId'].'</vendorId>
    <vendorName>'.$row['vendorName'].'</vendorName>
    <libraryId>'.$row['libraryId'].'</libraryId>
    <libraryName>'.$row['libraryName'].'</libraryName>
    <lessonId>'.$row['lessonId'].'</lessonId>
    <ej4CourseNumber>'.$row['ej4CourseNumber'].'</ej4CourseNumber>
    <lessonModality>'.$row['lessonModality'].'</lessonModality>
    <hsiProgramID>'.$row['hsiProgramID'].'</hsiProgramID>
    <lessonLength>'.$row['lessonLength'].'</lessonLength>
    <locale>'.$row['locale'].'</locale>
    <courseLanguageCategoriesSlug>'.$courseLanguagesSlugs.'</courseLanguageCategoriesSlug>
    <PricingTier>'.$row['PricingTier'].'</PricingTier>
    <courseImageUrl>'.$thumbnailLg.'</courseImageUrl>
    <courseImageThumbUrl>'.$thumbnailSm.'</courseImageThumbUrl>
    <courseInformation>
        <![CDATA['.$row['cldLessonDescription'].']]>
    </courseInformation>
    <marketingDescription>
        <![CDATA['.$mktDescription.']]>
    </marketingDescription>
    <courseOutline>
        '.$courseOutline.'
    </courseOutline>
    <courseObjectives>
        '.$courseObjectives.'
    </courseObjectives>
    <courseRegulations>
        '.$courseRegs.'
    </courseRegulations>
</course>';

        return $xmlString;
    }

    public function isInCoursesToDeletFromDaveCsvNov82023($cldid)
    {
        $database = $this->db();

        $count = $database->count('course_to_delete_from_dave_csv_nov_8_2023', [
            'cldid' => $cldid,
        ]);

        $exists = ($count > 0) ? true : false;

        return $exists;
    }

    public function entriesToDisableFeedAll()
    {
        $database = $this->db();
        header('Content-Type: application/xml; charset=utf-8');

        // $data = $database->select("course_data", "*");
        // $data = $database->select("course_data", "*", ["LIMIT" => [0, 1]]);
        $xmlString = '';
        $xmlString .= '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xmlString .= '<root>';
        $xmlString .= ' <courses>';

        // Build all rows that do not have language

        $data = $database->select('course_api_to_delete', '*');
        foreach ($data as $row) {
            $xmlString .= '<course>';
            $xmlString .= '<title>
                <![CDATA['.$row['title'].']]>
            </title>';
            // $xmlString .= '<craftid>' . $row['craftid'] . '</craftid>';
            $xmlString .= '<cldid>'.$row['cldid'].'</cldid>';
            $xmlString .= '</course>';
        }

        $xmlString .= '
    </courses>
</root>';
        $xmlString = trim($xmlString);
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xmlString);

        // Save XML as a file
        $dom->save(dirname(dirname(__FILE__)).'/xml/coursesToDisableFeedApi.xml');
    }

    public function entriesToDisableFeed()
    {
        $database = $this->db();
        header('Content-Type: application/xml; charset=utf-8');

        // $data = $database->select("course_data", "*");
        // $data = $database->select("course_data", "*", ["LIMIT" => [0, 1]]);
        $xmlString = '';
        $xmlString .= '
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xmlString .= '<root>';
        $xmlString .= ' <courses>';

        // Build all rows that do not have language

        $data = $database->select('course_to_delete', '*');
        foreach ($data as $row) {
            $xmlString .= '<course>';
            $xmlString .= '<title>
                <![CDATA['.$row['title'].']]>
            </title>';
            // $xmlString .= '<craftid>' . $row['craftid'] . '</craftid>';
            $xmlString .= '<cldid>'.$row['cldid'].'</cldid>';
            $xmlString .= '</course>';
        }

        $xmlString .= '
    </courses>
</root>';
        $xmlString = trim($xmlString);
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xmlString);

        // Save XML as a file
        $dom->save(dirname(dirname(__FILE__)).'/xml/coursesToDisableFeed.xml');
    }

    public function fullXmlFeedSingles()
    {
        $database = $this->db();
        header('Content-Type: application/xml; charset=utf-8');

        // $data = $database->select("course_data", "*");
        // $data = $database->select("course_data", "*", ["LIMIT" => [0, 1]]);
        $xmlString = '';
        $xmlString .= '
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xmlString .= '<root>';
        $xmlString .= ' <courses>';

        // Build all rows that do not have language
        $data = $database->select('course_api_data_singles', '*');
        foreach ($data as $row) {
            // if (!$this->isInCoursesToDeletFromDaveCsvNov82023($row['CatalogID'])) {
            $courseLanguagesSlugs = $this->getCourseLanguagesSlugs($row['CatalogID']);
            $xmlString .= $this->buildXmlRow($row, $courseLanguagesSlugs);
            // }
        }

        $data = $database->select('course_data_languages', '*', [
            'parentCldid' => null,
        ]);
        foreach ($data as $row) {
            // if (!$this->isInCoursesToDeletFromDaveCsvNov82023($row['CatalogID'])) {
            $courseLanguagesSlugs = $this->getLessonNameLanguageSlug($row['lessonName']);
            $xmlString .= $this->buildXmlRow($row, $courseLanguagesSlugs);
            // }
        }

        $xmlString .= '
    </courses>
</root>';
        $xmlString = trim($xmlString);
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xmlString);

        // Save XML as a file
        $dom->save(dirname(dirname(__FILE__)).'/xml/coursesFeedSingles.xml');
    }

    public function fullXmlFeed()
    {
        $database = $this->db();
        header('Content-Type: application/xml; charset=utf-8');

        // $data = $database->select("course_data", "*");
        // $data = $database->select("course_data", "*", ["LIMIT" => [0, 1]]);
        $xmlString = '';
        $xmlString .= '
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xmlString .= '<root>';
        $xmlString .= ' <courses>';

        // Build all rows that do not have language
        $data = $database->select('course_data', '*');
        foreach ($data as $row) {
            // if (!$this->isInCoursesToDeletFromDaveCsvNov82023($row['CatalogID'])) {
            $courseLanguagesSlugs = $this->getCourseLanguagesSlugs($row['CatalogID']);
            $xmlString .= $this->buildXmlRow($row, $courseLanguagesSlugs);
            // }
        }

        $data = $database->select('course_data_languages', '*', [
            'parentCldid' => null,
        ]);
        foreach ($data as $row) {
            // if (!$this->isInCoursesToDeletFromDaveCsvNov82023($row['CatalogID'])) {
            $courseLanguagesSlugs = $this->getLessonNameLanguageSlug($row['lessonName']);
            $xmlString .= $this->buildXmlRow($row, $courseLanguagesSlugs);
            // }
        }

        $xmlString .= '
    </courses>
</root>';
        $xmlString = trim($xmlString);
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xmlString);

        // Save XML as a file
        $dom->save(dirname(dirname(__FILE__)).'/xml/coursesFeed.xml');
    }

    public function listAllPdfFiles()
    {
        $dir = new DirectoryIterator(dirname(dirname(__FILE__)).'/reports/pdf');
        foreach ($dir as $fileinfo) {
            if (! $fileinfo->isDot()) {
                // var_dump($fileinfo->getFilename());
                echo '<br /><a target="_blank"
    href="https://apismd.hsi.com/reports/pdf/'.$fileinfo->getFilename().'">https://apismd.hsi.com/reports/pdf/'
                    .$fileinfo->getFilename()
                    .'<a />';
                echo '<br /><br />';
            }
        }
    }
}
$generic = new Generic;
