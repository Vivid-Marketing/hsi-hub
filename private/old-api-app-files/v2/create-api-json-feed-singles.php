<?php

/**
 * JSON feed from course_api_data_singles for Feed Me (specific IDs only).
 * Use this URL in Feed Me when using the singles feed; the XML feed is for file export only.
 */
require_once __DIR__.'/classes/cld-api.class.php';
$cldapi = new CldApi;
$cldapi->createFeedFromCoursesApiDBSingles();
