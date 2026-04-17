<?php

/**
 * Process specific course IDs only (no full batch fetch). Writes to course_api_data_staging.
 * Add IDs to $manualList; set runFeedMe to true to trigger the feed after processing.
 */
require_once __DIR__.'/classes/cld-api.class.php';
$cldapi = new CldApi;
$manualList = [7142];
$cldapi->cronJobGenerateAddUpdateCldApiDataFromList($manualList, 'prod', 70, true, false);
