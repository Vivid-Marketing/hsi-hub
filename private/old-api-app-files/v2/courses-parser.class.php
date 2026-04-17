<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once dirname(dirname(__FILE__)).'/vendor/autoload.php';
require dirname(dirname(__FILE__)).'/classes/generic.class.php';

use Dompdf\Dompdf;
use Postmark\PostmarkClient;

// require_once 'meekrodb.2.4.class.php';

class CoursesParser extends Generic
{
    public function __construct() {}

    private static $batchesTableEnsured = false;

    public function processPayloadStitchingBatchesCron()
    {
        $this->ensureCoursesPdfsBatchesTable();

        $database = parent::db();
        $jobLimit = 5;

        $sql = "
            SELECT job_id,
                   MIN(total_batches) AS total_batches,
                   COUNT(*) AS pending_batches,
                   MAX(email) AS email,
                   MIN(date_entered) AS first_seen
            FROM courses_pdfs_batches
            WHERE status = 'pending'
            GROUP BY job_id
            HAVING pending_batches = total_batches
            ORDER BY first_seen ASC
            LIMIT {$jobLimit}
        ";

        $statement = $database->query($sql);
        if (! $statement instanceof \PDOStatement) {
            error_log('Failed to fetch pending batch jobs.');

            return;
        }

        $jobs = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (! $jobs) {
            error_log('No complete batch jobs ready for stitching.');

            return;
        }

        foreach ($jobs as $job) {
            $jobId = $job['job_id'];
            $expectedBatches = (int) $job['total_batches'];

            $batches = $database->select('courses_pdfs_batches', '*', [
                'job_id' => $jobId,
                'status' => 'pending',
                'ORDER' => ['batch_index' => 'ASC'],
            ]);

            if (count($batches) !== $expectedBatches) {
                $this->markBatchJobAsFailed($jobId, "Expected {$expectedBatches} batches but found ".count($batches));

                continue;
            }

            $emails = array_unique(array_map(static function ($batch) {
                return $batch['email'];
            }, $batches));

            if (count($emails) !== 1) {
                $this->markBatchJobAsFailed($jobId, 'Mismatched email addresses across batches');

                continue;
            }

            $email = reset($emails);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->markBatchJobAsFailed($jobId, 'Invalid email on batch job');

                continue;
            }

            $combinedCourses = [];
            $failedBatches = [];

            foreach ($batches as $batchRow) {
                [$payload, $wasFixed] = $this->safeUnserialize($batchRow['serialized_data']);
                if (! is_array($payload)) {
                    $failedBatches[] = (int) $batchRow['batch_index'];

                    continue;
                }
                if ($wasFixed) {
                    error_log("Repaired serialized lengths for batch job {$jobId} batch {$batchRow['batch_index']}");
                }
                $combinedCourses = array_merge($combinedCourses, $payload);
            }

            if (! empty($failedBatches)) {
                $this->markBatchJobAsFailed($jobId, 'Failed to unserialize batches: '.implode(', ', $failedBatches));

                continue;
            }

            try {
                $database->insert('courses_pdfs_data', [
                    'serialized_data' => serialize($combinedCourses),
                    'date_entered' => date('Y-m-d H:i:s'),
                    'email' => $email,
                ]);
                $cpdid = $database->id();

                $database->update('courses_pdfs_batches', [
                    'status' => 'processed',
                    'processed_at' => date('Y-m-d H:i:s'),
                    'stitched_cpdid' => $cpdid,
                    'error_message' => null,
                ], [
                    'job_id' => $jobId,
                ]);

                error_log("Stitched {$expectedBatches} batches for job {$jobId} into cpdid {$cpdid}");
            } catch (\Throwable $e) {
                $this->markBatchJobAsFailed($jobId, 'Failed to insert stitched payload: '.$e->getMessage());
            }
        }
    }

    public function parseCoursesAndInputDBBatches()
    {
        $this->ensureCoursesPdfsBatchesTable();

        $input = file_get_contents('php://input');
        if ($input === '' && PHP_SAPI === 'cli') {
            $input = stream_get_contents(STDIN);
        }
        error_log('Raw POST data size: '.strlen($input).' bytes');

        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            $this->respondJsonAndExit([
                'status' => 'error',
                'message' => 'Invalid JSON payload',
            ], 400);
        }

        $jobId = isset($data['jobId']) ? trim((string) $data['jobId']) : '';
        $batchIndex = $data['batchIndex'] ?? null;
        $totalBatches = $data['totalBatches'] ?? null;
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $coursesPayload = $data['courses'] ?? null;

        if ($jobId === '' || strlen($jobId) > 128) {
            $this->respondJsonAndExit([
                'status' => 'error',
                'message' => 'Missing or invalid jobId',
            ], 422);
        }

        if (! is_numeric($batchIndex) || (int) $batchIndex < 0) {
            $this->respondJsonAndExit([
                'status' => 'error',
                'message' => 'Missing or invalid batchIndex',
            ], 422);
        }

        if (! is_numeric($totalBatches) || (int) $totalBatches <= 0) {
            $this->respondJsonAndExit([
                'status' => 'error',
                'message' => 'Missing or invalid totalBatches',
            ], 422);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respondJsonAndExit([
                'status' => 'error',
                'message' => 'Missing or invalid email',
            ], 422);
        }

        $batchIndex = (int) $batchIndex;
        $totalBatches = (int) $totalBatches;

        if ($batchIndex >= $totalBatches) {
            $this->respondJsonAndExit([
                'status' => 'error',
                'message' => 'batchIndex must be less than totalBatches',
            ], 422);
        }

        if (is_string($coursesPayload)) {
            $coursesData = json_decode($coursesPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($coursesData)) {
                $this->respondJsonAndExit([
                    'status' => 'error',
                    'message' => 'Invalid courses JSON',
                ], 422);
            }
        } elseif (is_array($coursesPayload)) {
            $coursesData = $coursesPayload;
        } else {
            $this->respondJsonAndExit([
                'status' => 'error',
                'message' => '"courses" must be an array or JSON string',
            ], 422);
        }

        $serializedData = serialize($coursesData);

        $database = parent::db();
        $now = date('Y-m-d H:i:s');

        if (PHP_SAPI === 'cli' && getenv('COURSES_BATCH_DEBUG')) {
            fwrite(STDERR, 'Saving batch with columns: '.implode(',', array_keys([
                'job_id' => $jobId,
                'batch_index' => $batchIndex,
                'total_batches' => $totalBatches,
                'email' => $email,
                'serialized_data' => '[binary]',
                'date_entered' => $now,
                'status' => 'pending',
                'error_message' => null,
                'processed_at' => null,
                'stitched_cpdid' => null,
            ]))."\n");
        }

        $batchKey = [
            'job_id' => $jobId,
            'batch_index' => $batchIndex,
        ];

        if ($database->has('courses_pdfs_batches', $batchKey)) {
            $database->update('courses_pdfs_batches', [
                'total_batches' => $totalBatches,
                'email' => $email,
                'serialized_data' => $serializedData,
                'date_entered' => $now,
                'status' => 'pending',
                'error_message' => null,
                'processed_at' => null,
                'stitched_cpdid' => null,
            ], $batchKey);
        } else {
            $database->insert('courses_pdfs_batches', [
                'job_id' => $jobId,
                'batch_index' => $batchIndex,
                'total_batches' => $totalBatches,
                'email' => $email,
                'serialized_data' => $serializedData,
                'date_entered' => $now,
                'status' => 'pending',
                'error_message' => null,
                'processed_at' => null,
                'stitched_cpdid' => null,
            ]);
        }

        $storedCount = $database->count('courses_pdfs_batches', [
            'job_id' => $jobId,
        ]);

        $pendingCount = $database->count('courses_pdfs_batches', [
            'job_id' => $jobId,
            'status' => 'pending',
        ]);

        $isComplete = $pendingCount >= $totalBatches;

        error_log("Stored batch {$batchIndex} for job {$jobId}. Pending {$pendingCount}/{$totalBatches}");

        $this->respondJsonAndExit([
            'status' => 'success',
            'message' => 'Batch stored',
            'jobId' => $jobId,
            'batchIndex' => $batchIndex,
            'totalBatches' => $totalBatches,
            'storedBatches' => $storedCount,
            'pendingBatches' => $pendingCount,
            'isComplete' => $isComplete,
        ]);
    }

    private function respondJsonAndExit(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function ensureCoursesPdfsBatchesTable(): void
    {
        if (self::$batchesTableEnsured) {
            return;
        }

        $database = parent::db();
        $sql = "
            CREATE TABLE IF NOT EXISTS courses_pdfs_batches (
                batch_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                job_id VARCHAR(128) NOT NULL,
                batch_index INT UNSIGNED NOT NULL,
                total_batches INT UNSIGNED NOT NULL,
                email VARCHAR(255) NOT NULL,
                serialized_data LONGTEXT NOT NULL,
                date_entered DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME NULL DEFAULT NULL,
                stitched_cpdid INT UNSIGNED NULL DEFAULT NULL,
                status ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
                error_message TEXT NULL,
                UNIQUE KEY uniq_job_batch (job_id, batch_index),
                KEY idx_job_id (job_id),
                KEY idx_status (status),
                KEY idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $database->query($sql);

        $requiredColumns = [
            'job_id' => 'VARCHAR(128) NOT NULL',
            'batch_index' => 'INT UNSIGNED NOT NULL',
            'total_batches' => 'INT UNSIGNED NOT NULL',
            'email' => 'VARCHAR(255) NOT NULL',
            'serialized_data' => 'LONGTEXT NOT NULL',
            'date_entered' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'processed_at' => 'DATETIME NULL DEFAULT NULL',
            'stitched_cpdid' => 'INT UNSIGNED NULL DEFAULT NULL',
            'status' => "ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending'",
            'error_message' => 'TEXT NULL',
        ];

        $existingColumns = [];
        $columnsStmt = $database->query('SHOW COLUMNS FROM courses_pdfs_batches');
        if ($columnsStmt instanceof \PDOStatement) {
            foreach ($columnsStmt->fetchAll(\PDO::FETCH_ASSOC) as $column) {
                $existingColumns[] = $column['Field'];
            }
        }

        $columnsList = implode(',', $existingColumns);
        error_log('courses_pdfs_batches existing columns: '.$columnsList);
        if (PHP_SAPI === 'cli' && getenv('COURSES_BATCH_DEBUG')) {
            fwrite(STDERR, "courses_pdfs_batches columns: {$columnsList}\n");
        }

        foreach ($requiredColumns as $column => $definition) {
            if (! in_array($column, $existingColumns, true)) {
                $database->query("ALTER TABLE courses_pdfs_batches ADD COLUMN {$column} {$definition}");
            }
        }

        self::$batchesTableEnsured = true;
    }

    private function markBatchJobAsFailed(string $jobId, string $message): void
    {
        $this->ensureCoursesPdfsBatchesTable();
        $database = parent::db();
        $database->update('courses_pdfs_batches', [
            'status' => 'failed',
            'error_message' => $message,
            'processed_at' => date('Y-m-d H:i:s'),
        ], [
            'job_id' => $jobId,
            'status' => 'pending',
        ]);
        error_log("Batch job {$jobId} failed: {$message}");
    }

    public function parseCoursesAndInputDB()
    {
        // Get the raw POST data
        $input = file_get_contents('php://input');
        error_log('Raw POST data size: '.strlen($input).' bytes');

        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data) || ! array_key_exists('courses', $data)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON or missing "courses" key']);
            exit;
        }

        // Decode the courses payload. Accept both raw arrays and JSON strings.
        $coursesPayload = $data['courses'];
        if (is_string($coursesPayload)) {
            $coursesData = json_decode($coursesPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid courses JSON']);
                exit;
            }
        } elseif (is_array($coursesPayload)) {
            $coursesData = $coursesPayload;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => '"courses" must be a JSON string or array']);
            exit;
        }

        $email = $data['email'] ?? null;

        if ($email === 'akresge-summers@gmail.com') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'You are not authorized']);
            exit;
        }

        if (! $email) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing email address']);
            exit;
        }

        if ($this->hasRecentSubmission($email)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'You’ve already submitted a request within the last hour.']);
            exit;
        }

        error_log('Received courses: '.count($coursesData).' records');

        // Serialize the data
        $serializedData = serialize($coursesData);
        error_log('Serialized data size: '.strlen($serializedData).' bytes');

        // Insert into database
        $database = parent::db();
        $database->insert('courses_pdfs_data', [
            'serialized_data' => $serializedData,
            'date_entered' => date('Y-m-d H:i:s'),
            'email' => $email,
        ]);

        // Get the last inserted ID (cpdid)
        $cpdid = $database->id();
        error_log('Inserted record with cpdid: '.$cpdid);

        // Decode and print for inspection
        $decodedArray = unserialize($serializedData);
        error_log('Decoded array size: '.count($decodedArray).' records');

        // $this->processPdfJobs($cpdid);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Data received and serialized',
            'received_records' => count($coursesData),
            'decoded_records' => count($decodedArray),
        ]);
    }

    public function hasRecentSubmission($email)
    {
        $database = parent::db();

        // Determine submission limit based on email domain
        $submissionLimit = str_ends_with($email, '@hsi.com') ? 5 : 2;

        // Get the count of submissions in the past hour
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $submissionCount = $database->count('courses_pdfs_data', [
            'AND' => [
                'email' => $email,
                'date_entered[>]' => $oneHourAgo,
            ],
        ]);

        return $submissionCount >= $submissionLimit;
    }

    public function getLanguageCode($languageName)
    {
        $languageMap = [
            'English' => 'en',
            'Spanish' => 'es',
            'French' => 'fr',
            'German' => 'de',
            'Italian' => 'it',
            'Portuguese' => 'pt',
            'Dutch' => 'nl',
            'Chinese' => 'zh',
            'Japanese' => 'ja',
            'Korean' => 'ko',
            'Russian' => 'ru',
            'Arabic' => 'ar',
            'Hindi' => 'hi',
            'Bengali' => 'bn',
            'Turkish' => 'tr',
            'Vietnamese' => 'vi',
            'Polish' => 'pl',
            'Persian' => 'fa',
            'Urdu' => 'ur',
            // Add more as needed
        ];

        $normalized = ucfirst(strtolower(trim($languageName)));

        return $languageMap[$normalized] ?? null;
    }

    public function parseCoursesArray($print = true, $cpdid = null)
    {
        $database = parent::db();
        if ($cpdid) {
            $rows = $database->select('courses_pdfs_data', '*', ['cpdid' => $cpdid]);
        } else {
            $rows = $database->select('courses_pdfs_data', '*', [
                'status' => null,
                'LIMIT' => [0, 1],
                'ORDER' => ['date_entered' => 'DESC'],
            ]);
        }

        // DO NOT remove 'collections' – we need it to group by Library.
        $keysToRemove = [
            'contentType',
            'url',
            'postDate',
            'courseInformation',
            'courseOutline',
            'courseRegulations',
            'courseLearningObjectives',
            'vendorName',
            'lessonModality',
            'subTopic',
            'courseCompletions',
            'courseImage',
            'objectID',
            '_highlightResult',
            '__position',
            // 'collections',  <-- keep this!
        ];

        $finalByLibrary = [];   // library => topic => subTopic => [courses]
        $coursesWithEmptyTopics = [];
        $coursesWithEmptySubTopics = [];

        foreach ($rows as $row) {
            $email = $row['email'];
            $cpdid = $row['cpdid'];

            $data = $row['serialized_data'];
            [$unserializeArray, $wasFixed] = $this->safeUnserialize($data);
            if ($unserializeArray === false) {
                error_log("Failed to unserialize row {$cpdid}");

                continue;
            }
            if ($wasFixed) {
                error_log("Repaired serialized lengths for row ID {$cpdid}");
            }

            // Normalize / filter the items we need
            $filtered = array_values(array_filter(array_map(function ($item) use ($keysToRemove, &$coursesWithEmptyTopics, &$coursesWithEmptySubTopics) {

                foreach ($keysToRemove as $key) {
                    unset($item[$key]);
                }

                if (! isset($item['topic']) || $item['topic'] === '') {
                    $coursesWithEmptyTopics[] = $item;

                    return null;
                }

                // Clean ID
                if (isset($item['cldID'])) {
                    $item['cldID'] = str_replace('CLD-', '', $item['cldID']);
                }

                // Languages: array -> CSV uppercase; or already CSV -> clean
                if (isset($item['courseLanguages']) && ! empty($item['courseLanguages'])) {
                    if (is_array($item['courseLanguages'])) {
                        $langs = [];
                        foreach ($item['courseLanguages'] as $lang) {
                            $langs[] = $this->getLanguageCode($lang);
                        }
                        $item['courseLanguages'] = strtoupper(implode(', ', array_filter($langs)));
                    } else {
                        // already string -> normalize spaces and duplicates
                        $langs = array_filter(array_map('trim', explode(',', $item['courseLanguages'])));
                        $item['courseLanguages'] = strtoupper(implode(', ', array_unique($langs)));
                    }
                } else {
                    $item['courseLanguages'] = '';
                }

                if (! isset($item['singleSubTopic']) || $item['singleSubTopic'] === '') {
                    $coursesWithEmptySubTopics[] = $item;
                    $item['singleSubTopic'] = 'Uncategorized';
                }

                // sanitize title
                if (isset($item['title'])) {
                    $item['title'] = str_replace('&', 'and', $item['title']);
                }

                return $item;
            }, $unserializeArray)));

            // ---- GROUP BY LIBRARY -> topic -> subtopic
            foreach ($filtered as $item) {
                $libraries = $this->extractLibraries($item); // implement this to read from $item['collections'] etc.
                if (empty($libraries)) {
                    $libraries = ['_No Library_']; // fallback if needed
                }

                $topic = $item['topic'];
                $subTopic = $item['singleSubTopic'] ?? 'Uncategorized';

                foreach ($libraries as $libraryName) {
                    $finalByLibrary[$libraryName][$topic][$subTopic][] = $item;
                }
            }
        }

        // ---- Sort library/topic/subtopic/title
        foreach ($finalByLibrary as $libraryName => &$topics) {
            ksort($topics, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($topics as $topic => &$subTopics) {
                ksort($subTopics, SORT_NATURAL | SORT_FLAG_CASE);
                foreach ($subTopics as $subTopic => &$items) {
                    usort($items, fn ($a, $b) => strcmp($a['title'], $b['title']));
                }
                unset($items);
            }
            unset($subTopics);
        }
        unset($topics);

        // ---- Flatten to the structure your PDF/HTML needs
        $librariesList = [];
        foreach ($finalByLibrary as $libraryName => $topics) {
            $topicList = [];
            foreach ($topics as $topicName => $subTopics) {
                $subTopicList = [];
                foreach ($subTopics as $subTopicName => $items) {
                    $subTopicList[] = [
                        'subTopic' => $subTopicName,
                        'subCourses' => $items,
                    ];
                }
                $topicList[] = [
                    'topic' => $topicName,
                    'subTopics' => $subTopicList,
                ];
            }
            $librariesList[] = [
                'library' => $libraryName,
                'topics' => $topicList,
            ];
        }

        $finalTemplateJson = [
            'libraries' => $librariesList,
        ];

        if ($print) {
            header('Content-Type: application/json');
            $jsonOutput = json_encode($finalTemplateJson, JSON_PRETTY_PRINT);
            if ($jsonOutput === false) {
                error_log('JSON encoding failed: '.json_last_error_msg());
                echo json_encode(['error' => 'Failed to encode JSON']);

                return $finalByLibrary;
            }
            echo $jsonOutput;

            return $finalByLibrary;
        }

        return [
            'cpdid' => $cpdid ?? null,
            'email' => $email ?? null,
            'coursesList' => $finalTemplateJson['libraries'],
        ];
    }

    private function safeUnserialize(string $data): array
    {
        $opts = ['allowed_classes' => false];
        $result = @unserialize($data, $opts);
        if ($result !== false || $data === 'b:0;') {
            return [$result, false];
        }
        $fixed = $this->fixSerializedString($data);
        $result = @unserialize($fixed, $opts);

        return [$result, $result !== false];
    }

    private function fixSerializedString(string $string): string
    {
        return preg_replace_callback(
            '!s:(\d+):"(.*?)";!s',
            static fn ($m) => 's:'.strlen($m[2]).':"'.$m[2].'";',
            $string
        );
    }

    /**
     * Extract the library names from the item. Adjust this based on how it is stored.
     */
    private function extractLibraries(array $item): array
    {
        if (! isset($item['collections']) || empty($item['collections'])) {
            return [];
        }
        // Example: collections = [ ['name' => 'Human Resources'], ... ]
        $names = [];
        foreach ($item['collections'] as $c) {
            if (is_array($c) && isset($c['name'])) {
                $names[] = $c['name'];
            } elseif (is_string($c)) {
                $names[] = $c;
            }
        }

        return array_values(array_unique($names));
    }

    public function getUseAnvilApiKeyDev()
    {
        return 'xHfmoQcptfl7rVrBQ3FBZvRxjk97A4dm';
    }

    public function getUseAnvilApiKeyProd()
    {
        return 'AsNFD75z9PskCGKxWN5WO0xo68Bbm5sB';
    }

    public function getPostMarkApiKey()
    {
        return $_ENV['POSTMARK_API_KEY'];
    }

    public function processPdfCron()
    {
        $limit = 10;
        // $cpdidsToProcess = [];
        $database = parent::db();
        $data = $database->select('courses_pdfs_data', '*', [
            'status' => null,
            'LIMIT' => [0, $limit],
            'ORDER' => ['date_entered' => 'DESC'],
        ]);
        foreach ($data as $job) {
            $this->processPdfJobs($job['cpdid']);
        }
    }

    public function processPdfJobs($cpdid = null)
    {
        $database = parent::db();
        $jobCoursesList = $this->parseCoursesArray(false, $cpdid);

        $cpdid = $jobCoursesList['cpdid'];
        $email = $jobCoursesList['email'];
        $sanitizedEmail = str_replace(['@', '.'], '-', $email);
        $coursesArray = $jobCoursesList['coursesList'];

        $bodyHtml = $this->getTemplateHtml($coursesArray);
        $stylesHtml = $this->getTemplateStyles();

        $html = '<html><head>'.$stylesHtml.'</head><body>'.$bodyHtml.'</body></html>';

        $data = [
            'type' => 'markdown',
            'title' => 'Courses Summary',
            'data' => [
                'html' => $html,
            ],
            'includeTimestamp' => true,
        ];

        $filename = str_replace(['@', '.'], '-', $email).'-'.date('Y-m-d-H-i-s');

        try {
            $filenameAndPath = $this->generateAndSaveServerPdf($data, $filename);
        } catch (Exception $e) {
            echo 'Error: '.$e->getMessage();
            exit();
        }

        // $filenameAndPath = dirname(dirname(__FILE__)) . "/courses-pdf/" . $filename . '.pdf';

        $cdnUrl = parent::uploadToDoSpaces($filenameAndPath, $filename.'.pdf', '	application/pdf');

        $client = new PostmarkClient($this->getPostMarkApiKey());

        $fileUrl = $cdnUrl;

        $msgHtml = '';

        $msgTxt = "Thank you for your interest! We've generated a PDF containing the current list of courses you were viewing. You can download it using the link below:\n\n".$fileUrl."\n\nWe hope this makes it easier for you to review and share the available courses. If you have any questions or need help finding the right course, feel free to reach out.";

        $msgHtml = $this->emailHtml('HSI Course Catalog PDF', $msgHtml, $fileUrl);
        $sendResult = $client->sendEmail(
            'noreply@hsi.com',     // From
            $email,  // To
            'HSI Courses Catalog - Download PDF',           // Subject
            $msgHtml, // HTML Body
            $msgTxt            // Text Body
        );
        if (! $cpdid) {
            print_r((array) $sendResult);
        } else {
            // Update status to 'sent'
            $database->update('courses_pdfs_data', [
                'status' => 'sent',
            ], [
                'cpdid' => $cpdid,
            ]);
            error_log("Updated status to 'sent' for cpdid: ".$cpdid);
        }
    }

    public function generateAndSaveServerPdf($data, $filename = 'output')
    {
        $html = $data['data']['html'];

        $dompdf = new Dompdf;
        $options = $dompdf->getOptions();
        $options->set(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);

        $dompdf->render();
        // $dompdf->stream();
        $output = $dompdf->output();
        $target_dir = dirname(dirname(__FILE__)).'/reports/courses/pdf/';
        $safeFilename = preg_replace('/\.pdf$/i', '', $filename);
        $pdfPath = $target_dir.$safeFilename.'.pdf';
        file_put_contents($pdfPath, $output);
        if (! empty($data['data']['html'])) {
            file_put_contents(dirname(dirname(__FILE__)).'/reports/courses/html/'.$safeFilename.'.html', $data['data']['html']);
        }

        return $pdfPath;
    }

    public function generateAndSaveAnvilPdf($data, $filename = 'output.pdf')
    {

        $apiKey = $this->getUseAnvilApiKeyProd();

        $url = 'https://app.useanvil.com/api/v1/generate-pdf';
        $jsonPayload = json_encode($data);
        $authHeader = 'Authorization: Basic '.base64_encode($apiKey.':');
        $headers = [
            'Content-Type: application/json',
            $authHeader,
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $pdfBinary = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        // Log or print debug output
        $log = [
            'request_url' => $url,
            'request_headers' => $headers,
            'request_body' => $jsonPayload,
            'response_code' => $httpCode,
            'curl_error' => $curlError,
            'response_body' => $pdfBinary,
        ];

        // Save debug output to a file
        // file_put_contents(__DIR__ . '/anvil_debug_log.json', json_encode($log, JSON_PRETTY_PRINT));

        if ($httpCode === 200 && $pdfBinary !== false) {
            $filenameAndPath = dirname(dirname(__FILE__)).'/courses-pdf/'.$filename;
            file_put_contents($filenameAndPath, $pdfBinary);
            if (! empty($data['data']['html'])) {
                file_put_contents(dirname(dirname(__FILE__)).'/courses-pdf/html/'.str_replace('.pdf', '.html', $filename), $data['data']['html']);
            }

            return $filename;
        } else {
            throw new Exception("Anvil API error or invalid response. Status code: $httpCode");
        }
    }

    public function generateAndSaveAnvilPdf_v1($data, $filename = 'output.pdf')
    {
        // $apiKey = $this->getUseAnvilApiKeyDev();
        $apiKey = $this->getUseAnvilApiKeyProd();

        $ch = curl_init('https://app.useanvil.com/api/v1/generate-pdf');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic '.base64_encode($apiKey.':'),
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Raw binary PDF returned
        $pdfBinary = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $pdfBinary !== false) {
            $filenameAndPath = dirname(dirname(__FILE__)).'/courses-pdf/'.$filename;
            file_put_contents($filenameAndPath, $pdfBinary);
            // Also save the HTML to a file for inspection
            if (! empty($data['data']['html'])) {
                file_put_contents(dirname(dirname(__FILE__)).'/courses-pdf/html/'.str_replace('.pdf', '.html', $filename), $data['data']['html']);
            }

            return $filename;
        } else {
            throw new Exception("Anvil API error or invalid response. Status code: $httpCode");
        }
    }

    public function getCoursesTable($coursesArray)
    {
        $finalHtml = '';

        foreach ($coursesArray as $library) {   // Top-level library loop
            // Library header
            // $finalHtml .= '<div style="background-color: #002b4f; width: 100%; padding: 12px; text-align: left;" class="library-header">'
            //     . '<h1 style="color: white; font-size: 16pt; font-weight: bold; margin: 0;">'
            //     . htmlspecialchars($library['library'])
            //     . '</h1></div>';

            // Topics under this library
            foreach ($library['topics'] as $topic) {
                $finalHtml .= '<div style="background-color: #003f6f; width: 100%; padding: 10px 0; text-align: left;" class="main-category">'
                    .'<h2 style="color: white; font-size: 14pt; font-weight: bold; margin: 0; text-align: left; padding-left: 10px;">'
                    .htmlspecialchars($topic['topic'])
                    .'</h2></div>';

                foreach ($topic['subTopics'] as $sub) {
                    $thStyle = 'background-color: #5fc2ff; font-size: 10pt; font-weight: bold; color: black; padding: 8px; text-align: left;';
                    $finalHtml .= '<table style="width: 100%; border-collapse: collapse;" class="sub-topic-table">
                    <thead>
                        <tr>
                            <th style="'.$thStyle.' width: 70%;">'.htmlspecialchars($sub['subTopic']).'</th>
                            <th style="'.$thStyle.' width: 10%;">ID</th>
                            <th style="'.$thStyle.' width: 10%;">Time</th>
                            <th style="'.$thStyle.' width: 10%;">Languages</th>
                        </tr>
                    </thead>
                </table>';

                    if (! empty($sub['subCourses'])) {
                        $finalHtml .= '<table style="width: 100%; border-collapse: collapse;" class="inner-table">';
                        foreach ($sub['subCourses'] as $course) {
                            $tdStyle = 'background-color: white; font-size: 9pt; color: black; font-weight: normal; padding: 8px; text-align: left;';
                            $finalHtml .= '<tr>
                            <td style="'.$tdStyle.' width: 70%;">'.htmlspecialchars($course['title']).'</td>
                            <td style="'.$tdStyle.' width: 10%;">'.htmlspecialchars($course['cldID']).'</td>
                            <td style="'.$tdStyle.' width: 10%;">'.htmlspecialchars($course['courseLength']).'</td>
                            <td style="'.$tdStyle.' width: 10%;">'.htmlspecialchars($course['courseLanguages']).'</td>
                        </tr>';
                        }
                        $finalHtml .= '</table>';
                    }
                }
            }
        }

        return $finalHtml;
    }

    public function getTemplateHtml($coursesArray)
    {
        $courseTableHtml = $this->getCoursesTable($coursesArray);
        $body = '<div style="text-align: center">
    <img src="https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/general/courses-catalog-main-image.png" alt=""
        width="768" height="434" />
</div>
<div style="text-align: center">&nbsp;</div>
<div style="text-align: left">
    <img src="https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/hsi-company-logos/HSI_Logo_Color_RGB_300x300.png" alt="" width="84" height="84" />
</div>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 70pt; color: #387bb6; font-weight: bold;">
    Course Catalog
</div>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 22pt; color: #000000;">
    One Partner. <strong>Multiple Workplace Solutions.</strong>
</div>
<div class="apitemplate-page-break" style="page-break-after: always"></div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 30pt; color: #387bb6; font-weight: bold;">
    The HSI Difference
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-size: 18pt; font-family: Arial, Helvetica, sans-serif; color: #5a1128;">
    Award-Winning Training
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; line-height: 1.5">
    The HSI course library contains over 5,000 of the most-effective Safety,
    Skills, and HR training titles available. Our full-length courses average
    15-55 minutes in length while our microlearning courses average 4-10 minutes.
    All of our rich, interactive courses ensure a front-of-the-seat learning
    experience, and come with the HSI LMS, or can be used in your own LMS.
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 18pt; color: #5a1128;">Engaging Content</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; line-height: 1.5">
    HSI builds courseware with production values that are second to none:
    vibrant, 3D animations, full narration, and reviewed by subject matter
    experts, so you know the content your employees receive can be trusted.
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; color: #5a1128; font-family: Arial, Helvetica, sans-serif; font-size: 18pt;">
    Always Current, Always Compliant
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; line-height: 1.5">
    HSI continuously updates our titles with new content, fresh imagery and
    improved features to ensure our courses remain accurate, relevant and
    engaging. HSI courses are also always compliant with updated OSHA, HR, and
    international regulations.
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 18pt; color: #5a1128;">
    AN EASY-TO-USE LEARNING MANAGEMENT SYSTEM
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; line-height: 1.5">
    The HSI LMS has an intuitive interface which allows employees to easily find
    and complete required training or identify elective courses they want to take.
    Administrator access makes assigning, tracking, and reporting on training
    programs simple. Back-end administrator access allows for customized training
    plans, out-of-the-box compliance reports, and the ability to load and assign
    your own training content.
     <img src="https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/general/lms-items.png" alt="lm-items"
        width="100%" height="auto" />
</div><div class="apitemplate-page-break" style="page-break-after: always"></div>'.$courseTableHtml;

        return $body;
    }

    public function getTemplateStyles()
    {
        return '<style>
         body {
  -webkit-font-smoothing: antialiased;
  background: #ffffff;
  font-family: "Arial" !important;
}

p {
    margin-block-start: 10px !important;
    margin-block-end: 10px !important;
}

hr {
  border: 1px solid #c2e0f4;
}

.main-category {
  background-color: #003f6f;
  width: 100%;
  padding: 10px 0px;
  text-align: left;
  font-family: "Arial", sans-serif;
}

.main-category h2 {
  color: white;
  font-size: 14pt;
  font-weight: bold;
  margin: 0;
  text-align: left;
  font-family: "Arial", sans-serif;
  padding-left: 10px;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-family: "Arial", sans-serif;
}

th {
  background-color: #5fc2ff;
  font-size: 10pt;
  font-weight: bold;
  color: black;
  padding: 8px;
  font-family: "Arial", sans-serif;
  text-align: left;
}

td {
  background-color: white;
  font-size: 8pt;
  color: black;
  font-weight: normal;
  font-family: "Arial", sans-serif;
  padding: 8px;
  text-align: left;
}

.col-1 {
  width: 70%;
}
.col-2 {
  text-align: center !important;
  justify-content: center;
}
.col-2,
.col-3,
.col-4 {
  width: 10%;
}

.footer {
  margin-top: 30px;
}

.footer-info {
  float: none;
  position: running(footer);
  margin-top: -25px;
}

.page-container .page::after {
  content: counter(page);
}

.page-container .pages::after {
  content: counter(pages);
}

.page-container {
  /* Define this element as a running element called "pageContainer" */
  position: running(pageContainer);
}

@page {
  /*
  Use any of these locations to place your margin elements:
  @top-left, @top-left-corner
  @top-center
  @top-right, @top-right-corner
  @bottom-left, @bottom-left-corner
  @bottom-center
  @bottom-right, @bottom-right-corner
  */
  @bottom-right {
    /*
    Reference "pageContainer" to be the content for the
    bottom right page margin area
    */
    content: element(pageContainer);
  }
}

.new-page {
  page-break-before: always;
}
</style>';
    }

    public function emailHtml($title, $body, $link)
    {
        return '<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
   <head>
      <title>
      '.$title.'
      </title>
      <!--[if !mso]><!-- -->
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <!--<![endif]-->
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style type="text/css">
         #outlook a {
         padding: 0;
         }
         .ReadMsgBody {
         width: 100%;
         }
         .ExternalClass {
         width: 100%;
         }
         .ExternalClass * {
         line-height: 100%;
         }
         body {
         margin: 0;
         padding: 0;
         -webkit-text-size-adjust: 100%;
         -ms-text-size-adjust: 100%;
         }
         table,
         td {
         border-collapse: collapse;
         mso-table-lspace: 0pt;
         mso-table-rspace: 0pt;
         }
         img {
         border: 0;
         height: auto;
         line-height: 100%;
         outline: none;
         text-decoration: none;
         -ms-interpolation-mode: bicubic;
         }
         p {
         display: block;
         margin: 0;
         margin-block-start: 10px;
         margin-block-end: 10px;   
         }
      </style>
      <!--[if !mso]><!-->
      <style type="text/css">
         @media only screen and (max-width:480px) {
         @-ms-viewport {
         width: 320px;
         }
         @viewport {
         width: 320px;
         }
         }
      </style>
      <!--<![endif]-->
      <!--[if mso]>
      <xml>
         <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
         </o:OfficeDocumentSettings>
      </xml>
      <![endif]-->
      <!--[if lte mso 11]>
      <style type="text/css">
         .outlook-group-fix { width:100% !important; }
      </style>
      <![endif]-->
      <style type="text/css">
         @media only screen and (min-width:480px) {
         .mj-column-per-100 {
         width: 100% !important;
         }
         }
      </style>
      <style type="text/css">
      </style>
   </head>
   <body style="background-color:#f9f9f9;">
      <div style="background-color:#f9f9f9;">
         <!--[if mso | IE]>
         <table
            align="center" border="0" cellpadding="0" cellspacing="0" style="width:600px;" width="600"
            >
            <tr>
               <td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
                  <![endif]-->
                  <div style="background:#f9f9f9;background-color:#f9f9f9;Margin:0px auto;max-width:600px;">
                     <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background:#f9f9f9;background-color:#f9f9f9;width:100%;">
                        <tbody>
                           <tr>
                              <td style="border-bottom:#333957 solid 5px;direction:ltr;font-size:0px;padding:20px 0;text-align:center;vertical-align:top;">
                                 <!--[if mso | IE]>
                                 <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                    </tr>
                                 </table>
                                 <![endif]-->
                              </td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
                  <!--[if mso | IE]>
               </td>
            </tr>
         </table>
         <table
            align="center" border="0" cellpadding="0" cellspacing="0" style="width:600px;" width="600"
            >
            <tr>
               <td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
                  <![endif]-->
                  <div style="background:#fff;background-color:#fff;Margin:0px auto;max-width:600px;">
                     <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background:#fff;background-color:#fff;width:100%;">
                        <tbody>
                           <tr>
                              <td style="border:#dddddd solid 1px;border-top:0px;direction:ltr;font-size:0px;padding:20px 0;text-align:center;vertical-align:top;">
                                 <!--[if mso | IE]>
                                 <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                       <td
                                          style="vertical-align:bottom;width:600px;"
                                          >
                                          <![endif]-->
                                          <div class="mj-column-per-100 outlook-group-fix" style="font-size:13px;text-align:left;direction:ltr;display:inline-block;vertical-align:bottom;width:100%;">
                                             <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="vertical-align:bottom;" width="100%">
                                                <tr>
                                                   <td align="center" style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                                      <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;border-spacing:0px;">
                                                         <tbody>
                                                            <tr>
                                                               <td style="width:64px;">
                                                                  <img height="auto" src="https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/hsi-company-logos/HSI_Logo_Color_RGB_300x300.png" style="border:0;display:block;outline:none;text-decoration:none;width:100%;" width="64" />
                                                               </td>
                                                            </tr>
                                                         </tbody>
                                                      </table>
                                                   </td>
                                                </tr>
                                                <tr>
                                                   <td align="center" style="font-size:0px;padding:10px 25px;padding-bottom:40px;word-break:break-word;">
                                                      <div style="font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:28px;font-weight:bold;line-height:normal;text-align:center;color:#555;">
                                                         Here\'s Your HSI Course Catalog 📄
                                                      </div>
                                                   </td>
                                                </tr>
                                                <tr>
                                                   <td align="left" style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                                      <div style="font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:16px;line-height:22px;text-align:left;color:#555;">
                                                         Hello,<br></br>
                                                         Thank you for your interest! We\'ve generated a PDF containing the current list of courses you were viewing. You can download it using the link below:
                                                      </div>
                                                   </td>
                                                </tr>
                                                <tr>
                                                   <td align="center" style="font-size:0px;padding:10px 25px;padding-top:30px;padding-bottom:50px;word-break:break-word;">
                                                      <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;line-height:100%;">
                                                         <tr>
                                                            <td align="center" bgcolor="#2F67F6" role="presentation" style="border:none;border-radius:3px;color:#ffffff;cursor:auto;" valign="middle">
                                                               <a href="'.$link.'" style="background:#2F67F6;color:#ffffff;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:15px;font-weight:normal;line-height:120%;Margin:0;text-decoration:none;text-transform:none;padding:15px 25px; display: inline-block;">
                                                                  Download Catalog PDF 📥
                                                               </a>
                                                            </td>
                                                         </tr>
                                                      </table>
                                                   </td>
                                                </tr>
                                                <tr>
                                                   <td align="left" style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                                      <div style="font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:16px;line-height:22px;text-align:left;color:#555;">
                                                         Interested in how HSI training can support your organization? Schedule a demo with one of our reps.
                                                      </div>
                                                   </td>
                                                </tr>
                                                <tr>
                                                   <td align="center" style="font-size:0px;padding:10px 25px;padding-top:30px;padding-bottom:50px;word-break:break-word;">
                                                      <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;line-height:100%;">
                                                         <tr>
                                                            <td align="center" bgcolor="#ffffff" role="presentation" style="border:none;border-radius:3px;color:#2F67F6;cursor:auto;" valign="middle">
                                                               <a href="https://hsi.com/contact" style="background:#ffffff;color:#2F67F6;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:15px;font-weight:normal;line-height:120%;Margin:0;text-decoration:none;text-transform:none;border: 1px solid #2f67f6; display: inline-block; padding: 15px 25px;">
                                                                  Schedule A Demo
                                                               </a>
                                                            </td>
                                                         </tr>
                                                      </table>
                                                   </td>
                                                </tr>
                                                <tr>
                                                   <td align="left" style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                                      <div style="font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:14px;line-height:20px;text-align:left;color:#525252;">
                                                         Best regards,<br><br> HSI Platform<br>Support Team<br>
                                                         <a href="https://hsi.com" style="color:#2F67F6">https://hsi.com</a>
                                                      </div>
                                                   </td>
                                                </tr>
                                             </table>
                                          </div>
                                          <!--[if mso | IE]>
                                       </td>
                                    </tr>
                                 </table>
                                 <![endif]-->
                              </td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
                  <!--[if mso | IE]>
               </td>
            </tr>
         </table>
         <![endif]-->
      </div>
   </body>
</html>';
    }
}
$courseparser = new CoursesParser;
